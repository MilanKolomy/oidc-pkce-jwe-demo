<?php

declare(strict_types=1);

namespace App\Token;

use DateTimeImmutable;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Encryption\Algorithm\ContentEncryption\A256GCM;
use Jose\Component\Encryption\Algorithm\KeyEncryption\Dir;
use Jose\Component\Encryption\Compression\CompressionMethodManager;
use Jose\Component\Encryption\JWEBuilder;
use Jose\Component\Encryption\Serializer\CompactSerializer;

/**
 * Issues the application's own token as a JWE (ADR-0003).
 *
 * Where Google's id_token is signed, this one is encrypted. It carries the internal
 * user identifier and travels through the browser repeatedly, so what has to be
 * protected here is confidentiality, not just origin. dir + A256GCM provides both:
 * the same application issues and reads it, so an asymmetric scheme would add
 * complexity without a benefit.
 *
 * The name and e-mail are deliberately absent — the API reads them from the
 * database, so the token carries no personal data beyond what it needs (NFR-07).
 */
final class TokenIssuer
{
    /**
     * Fifteen minutes, as specified in docs/03-navrh-reseni.md, section 4. A token
     * cannot be revoked before it expires, which is what keeps the window short.
     */
    public const LIFETIME_SECONDS = 900;

    /**
     * A constant rather than the application's URL: the token is issued and read by
     * the same deployment, and the key already ties it to one. Deriving the issuer
     * from a request header would let a proxy misconfiguration invalidate every
     * token in circulation.
     */
    public const ISSUER = 'oidc-pkce-jwe-demo';

    public function __construct(private readonly JWK $key)
    {
    }

    public function issue(int $userId, DateTimeImmutable $now): string
    {
        $claims = [
            'iss' => self::ISSUER,
            'sub' => (string) $userId,
            'iat' => $now->getTimestamp(),
            'exp' => $now->getTimestamp() + self::LIFETIME_SECONDS,
            'jti' => bin2hex(random_bytes(16)),
        ];

        $builder = new JWEBuilder(
            new AlgorithmManager([new Dir()]),
            new AlgorithmManager([new A256GCM()]),
            new CompressionMethodManager([])
        );

        $jwe = $builder->create()
            ->withPayload(json_encode($claims, JSON_THROW_ON_ERROR))
            ->withSharedProtectedHeader(['alg' => 'dir', 'enc' => 'A256GCM'])
            ->addRecipient($this->key)
            ->build();

        return (new CompactSerializer())->serialize($jwe, 0);
    }
}
