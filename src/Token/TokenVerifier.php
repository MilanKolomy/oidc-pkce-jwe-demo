<?php

declare(strict_types=1);

namespace App\Token;

use App\Exception\UnauthorizedException;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256GCM;
use Jose\Component\Encryption\Algorithm\KeyEncryption\Dir;
use Jose\Component\Encryption\Compression\CompressionMethodManager;
use Jose\Component\Encryption\JWEDecrypter;
use Jose\Component\Encryption\Serializer\CompactSerializer;
use Throwable;

/**
 * Reads back the token this application issued (ADR-0003).
 *
 * Decryption with A256GCM authenticates as well as conceals: a token that has been
 * altered by even one byte fails to decrypt, so no separate signature is needed.
 *
 * No database is consulted. That was the point of issuing an own token rather than
 * reusing Google's — the API can authorize a request without asking anyone.
 *
 * Every failure is the same 401 to the caller. Which of them it was goes to the log,
 * never to the response: the difference between "expired" and "not decryptable" is
 * information about the application, and the caller cannot act on it either way.
 */
final class TokenVerifier
{
    private const TAG_BYTES = 16;

    public function __construct(private readonly JWK $key)
    {
    }

    /**
     * @return int the internal user identifier from the sub claim
     * @throws UnauthorizedException
     */
    public function verify(string $token): int
    {
        $decrypter = new JWEDecrypter(
            // Fixed to the algorithms the issuer uses. Taking them from the token's
            // own header would let the token choose how it is checked.
            new AlgorithmManager([new Dir()]),
            new AlgorithmManager([new A256GCM()]),
            new CompressionMethodManager([])
        );

        try {
            // A JWS has three segments and will not unserialize here, so a merely
            // signed token cannot be presented in place of an encrypted one.
            $jwe = (new CompactSerializer())->unserialize($token);

            // A256GCM has a 128 bit authentication tag, and the whole integrity
            // guarantee rests on it. OpenSSL accepts shorter GCM tags, so a token whose
            // tag has simply been cut short still decrypts — with the forgery odds
            // dropping from 2^-128 to whatever is left. Checked here, because the
            // library does not check it.
            if (strlen((string) $jwe->getTag()) !== self::TAG_BYTES) {
                throw new UnauthorizedException(
                    'Session token is missing or expired.',
                    'the authentication tag is not ' . self::TAG_BYTES . ' bytes',
                );
            }

            $decrypted = $decrypter->decryptUsingKey($jwe, $this->key, 0);
        } catch (UnauthorizedException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new UnauthorizedException('Session token is missing or expired.', $exception->getMessage());
        }

        if (!$decrypted) {
            throw new UnauthorizedException('Session token is missing or expired.', 'the token could not be decrypted');
        }

        $claims = json_decode((string) $jwe->getPayload(), true);

        if (!is_array($claims)) {
            throw new UnauthorizedException('Session token is missing or expired.', 'the payload is not a JSON object');
        }

        return $this->subjectFrom($claims);
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function subjectFrom(array $claims): int
    {
        if (($claims['iss'] ?? null) !== TokenIssuer::ISSUER) {
            throw new UnauthorizedException('Session token is missing or expired.', 'unexpected issuer');
        }

        $now = time();
        $expiry = $claims['exp'] ?? null;

        // No clock skew allowance here, unlike the id_token: this token is issued and
        // read by the same machine, so there are no two clocks to disagree.
        if (!is_int($expiry) || $now >= $expiry) {
            throw new UnauthorizedException('Session token is missing or expired.', 'the token has expired');
        }

        $issuedAt = $claims['iat'] ?? null;

        if (!is_int($issuedAt) || $issuedAt > $now) {
            throw new UnauthorizedException('Session token is missing or expired.', 'the token is issued in the future');
        }

        $subject = $claims['sub'] ?? null;

        if (!is_string($subject) || preg_match('/^[1-9]\d*$/', $subject) !== 1) {
            throw new UnauthorizedException('Session token is missing or expired.', 'the subject is not a user identifier');
        }

        return (int) $subject;
    }
}
