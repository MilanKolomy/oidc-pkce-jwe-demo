<?php

declare(strict_types=1);

use App\Api\Authentication;
use App\Api\AuthorityController;
use App\Api\CertificateController;
use App\Api\UserController;
use App\Certificate\CertificateParser;
use App\Certificate\ValidityChecker;
use App\Config\Config;
use App\Exception\ConfigurationException;
use App\Exception\HttpException;
use App\Http\Problem;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Http\Session;
use App\Http\UrlBuilder;
use App\Log\Logger;
use App\Oidc\Discovery;
use App\Oidc\HttpClient;
use App\Oidc\IdTokenValidator;
use App\Oidc\JwksCache;
use App\Oidc\TokenClient;
use App\Persistence\CertificateAuthorityRepository;
use App\Persistence\CertificateCheckRepository;
use App\Persistence\CertificateRepository;
use App\Persistence\Database;
use App\Persistence\KeyUsageRepository;
use App\Persistence\UserRepository;
use App\Token\TokenIssuer;
use App\Token\TokenKey;
use App\Token\TokenVerifier;
use App\Web\AuthController;
use App\Web\CertificateController as PageCertificateController;
use App\Web\ProfileController;
use App\Web\SpecificationController;
use App\Web\View;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);

// Identifies this request in the log and in the 500 response, so a production
// failure can be traced back to its entry without the response carrying the
// cause (OMZ-04).
$correlationId = bin2hex(random_bytes(8));

$request = Request::fromGlobals();
$logger = new Logger($root . '/var/app.log', $correlationId);
$config = null;
$view = null;

/**
 * A browser gets a page, /api/ gets RFC 7807 — for failures as well, which is where
 * the difference matters most: someone who followed a link is least able to do
 * anything with a JSON document.
 *
 * Returns null when a page cannot be produced, either because the caller wants JSON
 * or because rendering one failed. Rendering is guarded on purpose: an error raised
 * inside the error handler would otherwise escape it, and the response would go out
 * as 200 with a stack trace in the body.
 */
$asPage = static function (int $status, string $heading, ?string $detail, ?string $failureId) use (&$view, $request): ?Response {
    if ($request->isApi() || !$view instanceof View) {
        return null;
    }

    try {
        return Response::html($view->page('error', $heading, null, [
            'status' => $status,
            'heading' => $heading,
            'detail' => $detail ?? '',
            'correlationId' => $failureId,
        ]), $status);
    } catch (Throwable) {
        return null;
    }
};

try {
    $config = Config::load($root . '/.env');

    Session::configure();

    $urls = new UrlBuilder($_SERVER);
    $httpClient = new HttpClient();
    $database = new Database($config);

    $tokenKey = TokenKey::fromFile($root . '/keys/app-token.key');
    $authentication = new Authentication(new TokenVerifier($tokenKey));
    $view = new View($root . '/templates');

    $auth = new AuthController(
        $authentication,
        $view,
        new Discovery($httpClient),
        new TokenClient($httpClient, $config->googleClientId, $config->googleClientSecret),
        new IdTokenValidator(new JwksCache($httpClient, $root . '/var/jwks.json'), $config->googleClientId),
        new UserRepository($database),
        new TokenIssuer($tokenKey),
        $urls,
        $logger,
        $config->googleClientId,
    );

    $users = new UserRepository($database);
    $certificateRepository = new CertificateRepository($database);
    $authorityRepository = new CertificateAuthorityRepository($database);
    $keyUsageRepository = new KeyUsageRepository($database);
    $checkRepository = new CertificateCheckRepository($database);
    $parser = new CertificateParser();
    $validity = new ValidityChecker();

    $certificates = new CertificateController(
        $authentication,
        $database,
        $certificateRepository,
        $authorityRepository,
        $keyUsageRepository,
        $checkRepository,
        $parser,
        $validity,
        $urls,
        $logger,
    );

    $pages = new PageCertificateController(
        $authentication,
        $view,
        $database,
        $users,
        $certificateRepository,
        $authorityRepository,
        $keyUsageRepository,
        $checkRepository,
        $parser,
        $validity,
        $logger,
    );

    $router = new Router();

    // Pages. The one for adding a certificate is registered before the one for a
    // certificate by identifier, because "new" would otherwise match the placeholder.
    $router->add('GET', '/', $auth->home(...));
    $router->add('GET', '/login', $auth->login(...));
    $router->add('GET', '/callback', $auth->callback(...));
    $router->add('GET', '/logout', $auth->logout(...));
    $router->add('GET', '/certificates', $pages->index(...));
    $router->add('GET', '/certificates/new', $pages->form(...));
    $router->add('POST', '/certificates', $pages->create(...));
    $router->add('GET', '/certificates/{certificateId}', $pages->show(...));
    $router->add('POST', '/certificates/{certificateId}/checks', $pages->check(...));
    $router->add('GET', '/profile', (new ProfileController($authentication, $view, $users, $certificateRepository))->show(...));
    $router->add('GET', '/swagger/openapi.yaml', (new SpecificationController($root . '/docs/openapi.yaml'))->show(...));

    // The six endpoints of docs/openapi.yaml, and nothing beyond them.
    $router->add('GET', '/api/v1/me', (new UserController($authentication, $users))->show(...));
    $router->add('GET', '/api/v1/certificates', $certificates->index(...));
    $router->add('POST', '/api/v1/certificates', $certificates->create(...));
    $router->add('GET', '/api/v1/certificates/{certificateId}', $certificates->show(...));
    $router->add('POST', '/api/v1/certificates/{certificateId}/checks', $certificates->check(...));
    $router->add('GET', '/api/v1/authorities', (new AuthorityController($authentication, new CertificateAuthorityRepository($database)))->index(...));

    $response = $router->dispatch($request);
} catch (HttpException $exception) {
    // Expected outcomes described in docs/openapi.yaml, not failures, so they are not
    // logged by default — a stream of 404s would bury the entries that matter. The
    // exceptions that deliberately tell the caller less than they know carry the real
    // reason, and that much is worth keeping.
    if ($exception->logReason() !== null) {
        $logger->warning(sprintf('%d %s', $exception->status(), $exception->title()), [
            'reason' => $exception->logReason(),
            'method' => $request->method,
            'path' => $request->path,
        ]);
    }

    $response = $asPage($exception->status(), $exception->title(), $exception->detail(), null)
        ?? Problem::fromException($exception, $request->path);
} catch (ConfigurationException $exception) {
    // Startup failed before the environment was known, so the response is the careful
    // one either way. The message names what is missing, never a value, and goes to
    // the log — where an operator without shell access can still reach it.
    $logger->error('Configuration error: ' . $exception->getMessage());

    // $view does not exist yet at this point, so this is a JSON answer in practice.
    // Asked through the same path anyway, so the rule stays in one place.
    $response = $asPage(500, 'Something went wrong', null, $correlationId)
        ?? Problem::internal($request->path, $correlationId);
} catch (Throwable $exception) {
    $logger->error(sprintf('Unhandled %s: %s', $exception::class, $exception->getMessage()), [
        'file' => $exception->getFile() . ':' . $exception->getLine(),
        'method' => $request->method,
        'path' => $request->path,
    ]);

    // If the failure happened before the configuration was read, the environment is
    // unknown — say nothing, which is the production behaviour.
    $detail = ($config?->isProduction() ?? true) ? null : $exception->getMessage();

    $response = $asPage(500, 'Something went wrong', $detail, $correlationId)
        ?? Problem::internal($request->path, $correlationId, $detail);
}

$response->send();
