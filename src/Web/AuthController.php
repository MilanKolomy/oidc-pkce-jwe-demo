<?php

declare(strict_types=1);

namespace App\Web;

use App\Api\Authentication;
use App\Exception\BadRequestException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Session;
use App\Http\UrlBuilder;
use App\Log\Logger;
use App\Oidc\Discovery;
use App\Oidc\IdTokenValidator;
use App\Oidc\Pkce;
use App\Oidc\TokenClient;
use App\Persistence\UserRepository;
use App\Token\TokenIssuer;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * The login flow: /login starts it, /callback finishes it and issues the
 * application's own token.
 */
final class AuthController
{
    private const SESSION_STATE = 'oidc_state';
    private const SESSION_NONCE = 'oidc_nonce';
    private const SESSION_VERIFIER = 'oidc_code_verifier';

    public function __construct(
        private readonly Authentication $authentication,
        private readonly View $view,
        private readonly Discovery $discovery,
        private readonly TokenClient $tokenClient,
        private readonly IdTokenValidator $idTokenValidator,
        private readonly UserRepository $users,
        private readonly TokenIssuer $tokenIssuer,
        private readonly UrlBuilder $urls,
        private readonly Logger $logger,
        private readonly string $clientId,
    ) {
    }

    /**
     * The sign-in page, or the list for someone already signed in.
     */
    public function home(Request $request): Response
    {
        if ($this->authentication->optionalUserId($request) !== null) {
            return Response::redirect('/certificates');
        }

        return Response::html($this->view->page('login', 'Sign in', null));
    }

    /**
     * Ends the local session and discards the token.
     *
     * Google is not signed out of: it offers no client-initiated logout worth relying
     * on, so the user stays signed in there. Said plainly in the documentation rather
     * than implied (docs/03-navrh-reseni.md, section 10).
     */
    public function logout(): Response
    {
        Session::start();

        $_SESSION = [];
        session_destroy();

        setcookie(Authentication::COOKIE_NAME, '', [
            'expires' => 1,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        return Response::redirect('/');
    }

    public function login(): Response
    {
        Session::start();

        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));
        $verifier = Pkce::createVerifier();

        // All three live in the server session, never in the request. A callback
        // without the matching session is refused, which is what makes the flow
        // resistant to a forged return (ADR-0002).
        Session::set(self::SESSION_STATE, $state);
        Session::set(self::SESSION_NONCE, $nonce);
        Session::set(self::SESSION_VERIFIER, $verifier);

        $parameters = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => Pkce::challengeFor($verifier),
            'code_challenge_method' => 'S256',
        ];

        $this->logger->info('Login started.');

        return Response::redirect(
            $this->discovery->authorizationEndpoint() . '?' . http_build_query($parameters)
        );
    }

    public function callback(Request $request): Response
    {
        Session::start();

        if (isset($request->query['error'])) {
            // Reported by the provider, for example when the user declined consent.
            $this->logger->warning('Login refused by the provider.', ['error' => $request->query['error']]);

            throw new BadRequestException('Sign-in was not completed at Google.');
        }

        $code = $request->query['code'] ?? '';
        $returnedState = $request->query['state'] ?? '';

        // Taken, not read: each value is good for exactly one callback, so a repeated
        // return cannot reuse them.
        $expectedState = Session::take(self::SESSION_STATE);
        $nonce = Session::take(self::SESSION_NONCE);
        $verifier = Session::take(self::SESSION_VERIFIER);

        if ($expectedState === null || $nonce === null || $verifier === null) {
            $this->logger->warning('Callback arrived without a matching session.');

            throw new BadRequestException('Your sign-in took too long or the session was lost. Please start again.');
        }

        if ($code === '' || !hash_equals($expectedState, $returnedState)) {
            $this->logger->warning('Callback rejected: state mismatch or missing code.');

            throw new BadRequestException('The sign-in response could not be matched to this session.');
        }

        try {
            $idToken = $this->tokenClient->exchangeCode(
                $this->discovery->tokenEndpoint(),
                $code,
                $verifier,
                $this->redirectUri(),
            );

            $claims = $this->idTokenValidator->validate(
                $idToken,
                $this->discovery->issuer(),
                $this->discovery->jwksUri(),
                $nonce,
            );
        } catch (Throwable $exception) {
            // The real reason goes to the log; the user gets something they can act on.
            // Neither the code nor the token is ever included.
            $this->logger->error('Login failed during token exchange or validation.', [
                'reason' => $exception::class . ': ' . $exception->getMessage(),
            ]);

            throw new BadRequestException('Sign-in could not be completed. Please try again.');
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $userId = $this->storeUser($claims, $now);

        // A session identifier seen before authentication must not remain valid after it.
        Session::regenerate();

        $token = $this->tokenIssuer->issue($userId, $now);

        setcookie(Authentication::COOKIE_NAME, $token, [
            'expires' => $now->getTimestamp() + TokenIssuer::LIFETIME_SECONDS,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $this->logger->info('Login succeeded.', ['user_id' => $userId]);

        return Response::redirect($this->urls->absolute('/'));
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function storeUser(array $claims, DateTimeImmutable $now): int
    {
        $googleSub = (string) $claims['sub'];
        $email = $claims['email'] ?? null;

        if (!is_string($email) || $email === '') {
            throw new BadRequestException('Google did not provide an e-mail address for this account.');
        }

        $displayName = is_string($claims['name'] ?? null) ? $claims['name'] : null;

        $existing = $this->users->findByGoogleSub($googleSub);

        if ($existing === null) {
            return $this->users->insert($googleSub, $email, $displayName, $now);
        }

        $userId = (int) $existing['id'];
        $this->users->updateOnLogin($userId, $email, $displayName, $now);

        return $userId;
    }

    /**
     * Composed by the one component that reads the proxy header, because Google
     * compares this value for an exact match (OMZ-03).
     */
    private function redirectUri(): string
    {
        return $this->urls->absolute('/callback');
    }
}
