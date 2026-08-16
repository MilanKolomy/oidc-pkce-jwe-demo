<?php

declare(strict_types=1);

namespace App\Http;

/**
 * The only place in the application that builds absolute URLs.
 *
 * Production runs behind a reverse proxy that reports an unencrypted connection
 * in the environment variables while the client actually speaks HTTPS (OMZ-03).
 * An address built from REQUEST_SCHEME would start with http://, and Google
 * compares the redirect URI for an exact match — so login would fail, and only
 * in production, where there is a proxy.
 *
 * REQUEST_SCHEME is therefore never consulted. The proxy header is authoritative;
 * the HTTPS flag is the verified fallback for environments without a proxy.
 */
final class UrlBuilder
{
    /**
     * @param array<string, mixed> $server
     */
    public function __construct(private readonly array $server)
    {
    }

    public function absolute(string $path): string
    {
        return $this->scheme() . '://' . $this->host() . '/' . ltrim($path, '/');
    }

    private function scheme(): string
    {
        $forwarded = $this->server['HTTP_X_FORWARDED_PROTO'] ?? null;

        if (is_string($forwarded) && $forwarded !== '') {
            // A proxy may forward a comma-separated list; the client-facing value is first.
            $scheme = strtolower(trim(explode(',', $forwarded)[0]));

            if ($scheme === 'https' || $scheme === 'http') {
                return $scheme;
            }
        }

        $https = $this->server['HTTPS'] ?? '';

        return is_string($https) && $https !== '' && strtolower($https) !== 'off' ? 'https' : 'http';
    }

    private function host(): string
    {
        $host = $this->server['HTTP_HOST'] ?? '';

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }
}
