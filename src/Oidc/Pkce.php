<?php

declare(strict_types=1);

namespace App\Oidc;

/**
 * Proof Key for Code Exchange, method S256 (ADR-0002).
 *
 * The verifier stays on the server and only its digest travels to the provider,
 * so a stolen authorization code is useless without the session that started the
 * flow. The plain method is deliberately not implemented: sending the value itself
 * would make the protection only apparent.
 */
final class Pkce
{
    /**
     * 32 random bytes, which base64url encodes to 43 characters — within the 43 to
     * 128 the specification allows.
     */
    public static function createVerifier(): string
    {
        return self::base64Url(random_bytes(32));
    }

    public static function challengeFor(string $verifier): string
    {
        return self::base64Url(hash('sha256', $verifier, true));
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
