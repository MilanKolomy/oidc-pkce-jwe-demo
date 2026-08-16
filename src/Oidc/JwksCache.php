<?php

declare(strict_types=1);

namespace App\Oidc;

use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use RuntimeException;
use Throwable;

/**
 * The provider's public keys, cached in a file.
 *
 * Two constraints shape this class. The cache is an optional speed-up, never a
 * condition of working: there is no shared cache service on the hosting, and the
 * application has to run with the cache empty, unreadable or unwritable (OMZ-07).
 * And an unknown key identifier must trigger a refresh, because Google rotates its
 * keys and a stale cache would otherwise stop logins until it expired.
 *
 * A miss on the key identifier covers a key being added. It does not cover one being
 * withdrawn: a cached kid stays known, nothing is fetched, and the application would
 * go on trusting a key the provider no longer publishes — indefinitely, including a
 * key withdrawn because it was compromised. The expiry below is therefore not a guess
 * at how long a key lives, but an upper bound on trust in a key that may be gone.
 */
final class JwksCache
{
    private const MAX_AGE_SECONDS = 86400;

    public function __construct(
        private readonly HttpClient $client,
        private readonly string $cacheFile,
    ) {
    }

    public function keyFor(string $kid, string $jwksUri): JWK
    {
        $cached = $this->readCache();

        if ($cached instanceof JWKSet && $cached->has($kid)) {
            return $cached->get($kid);
        }

        $fresh = $this->fetch($jwksUri);

        if (!$fresh->has($kid)) {
            throw new RuntimeException(sprintf('The provider has no key with id "%s".', $kid));
        }

        return $fresh->get($kid);
    }

    private function fetch(string $jwksUri): JWKSet
    {
        $keys = $this->client->getJson($jwksUri);
        $set = JWKSet::createFromKeyData($keys);

        $this->writeCache($keys);

        return $set;
    }

    private function readCache(): ?JWKSet
    {
        // is_file, not is_readable: a directory is readable, and reading one raises a
        // warning that would be printed into the response body.
        if (!is_file($this->cacheFile)) {
            return null;
        }

        try {
            $decoded = json_decode((string) @file_get_contents($this->cacheFile), true);

            if (!is_array($decoded)) {
                return null;
            }

            $fetchedAt = $decoded['fetched_at'] ?? null;

            if (!is_int($fetchedAt) || time() - $fetchedAt >= self::MAX_AGE_SECONDS) {
                return null;
            }

            return JWKSet::createFromKeyData($decoded);
        } catch (Throwable) {
            // A damaged cache is treated as no cache. Failing here would turn a
            // speed-up into a single point of failure.
            return null;
        }
    }

    /**
     * @param array<string, mixed> $keys
     */
    private function writeCache(array $keys): void
    {
        $directory = dirname($this->cacheFile);

        if (!is_dir($directory)) {
            @mkdir($directory, 0o775, true);
        }

        // Best effort: the public keys are public, and being unable to store them
        // costs a request, not correctness.
        @file_put_contents(
            $this->cacheFile,
            json_encode(['fetched_at' => time()] + $keys),
            LOCK_EX
        );
    }
}
