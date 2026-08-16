<?php

declare(strict_types=1);

namespace App\Oidc;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use RuntimeException;

/**
 * Verifies the identity assertion issued by Google.
 *
 * The token is a JWS: what matters is that nobody forged it, not that nobody read
 * it. The name and e-mail it carries are not secret — the user gave them to Google
 * themselves (docs/03-navrh-reseni.md, section 4).
 */
final class IdTokenValidator
{
    /**
     * Clock skew allowance, as required by CLAUDE.md section 8.
     */
    private const LEEWAY_SECONDS = 60;

    public function __construct(
        private readonly JwksCache $jwks,
        private readonly string $clientId,
    ) {
    }

    /**
     * @return array<string, mixed> the verified claims
     */
    public function validate(string $idToken, string $issuer, string $jwksUri, string $expectedNonce): array
    {
        $jws = (new CompactSerializer())->unserialize($idToken);
        $header = $jws->getSignature(0)->getProtectedHeader();

        $algorithm = $header['alg'] ?? null;

        // Only RS256 is accepted. Taking the algorithm from the header without
        // restricting it is how "alg": "none" and key confusion attacks get in.
        if ($algorithm !== 'RS256') {
            throw new RuntimeException(sprintf('Unexpected id_token algorithm "%s".', (string) $algorithm));
        }

        $kid = $header['kid'] ?? null;

        if (!is_string($kid) || $kid === '') {
            throw new RuntimeException('The id_token header carries no key id.');
        }

        // An unknown kid makes JwksCache fetch the key set again, so a key rotation
        // on Google's side does not stop logins (OMZ-07).
        $key = $this->jwks->keyFor($kid, $jwksUri);

        $verifier = new JWSVerifier(new AlgorithmManager([new RS256()]));

        if (!$verifier->verifyWithKey($jws, $key, 0)) {
            throw new RuntimeException('The id_token signature is not valid.');
        }

        $claims = json_decode((string) $jws->getPayload(), true);

        if (!is_array($claims)) {
            throw new RuntimeException('The id_token payload is not a JSON object.');
        }

        $this->assertClaims($claims, $issuer, $expectedNonce);

        return $claims;
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function assertClaims(array $claims, string $issuer, string $expectedNonce): void
    {
        $now = time();

        // Google states the issuer both with and without the scheme. Both forms are
        // derived from the discovery document, never accepted as a free-form value.
        $acceptedIssuers = [$issuer, (string) preg_replace('#^https://#', '', $issuer)];

        if (!in_array($claims['iss'] ?? null, $acceptedIssuers, true)) {
            throw new RuntimeException('The id_token was issued by an unexpected issuer.');
        }

        $audience = $claims['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];

        if (!in_array($this->clientId, $audiences, true)) {
            throw new RuntimeException('The id_token is addressed to a different client.');
        }

        $expiry = $claims['exp'] ?? null;

        if (!is_int($expiry) || $now >= $expiry + self::LEEWAY_SECONDS) {
            throw new RuntimeException('The id_token has expired.');
        }

        $issuedAt = $claims['iat'] ?? null;

        if (!is_int($issuedAt) || $issuedAt > $now + self::LEEWAY_SECONDS) {
            throw new RuntimeException('The id_token is issued in the future.');
        }

        // Compared in constant time, and against the value this session generated:
        // it is what ties the assertion to the request that asked for it.
        $nonce = $claims['nonce'] ?? null;

        if (!is_string($nonce) || !hash_equals($expectedNonce, $nonce)) {
            throw new RuntimeException('The id_token nonce does not match this session.');
        }

        $subject = $claims['sub'] ?? null;

        if (!is_string($subject) || $subject === '') {
            throw new RuntimeException('The id_token carries no subject.');
        }
    }
}
