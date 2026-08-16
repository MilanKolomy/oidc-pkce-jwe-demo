<?php

declare(strict_types=1);

namespace App\Oidc;

use RuntimeException;

/**
 * The three calls this application makes to the identity provider: the discovery
 * document, the key set and the token exchange.
 *
 * Written on ext-curl, which docs/02-omezeni-prostredi.md verified as present and
 * names for exactly this purpose. Certificate verification is left on: turning it
 * off would make the whole flow meaningless.
 */
final class HttpClient
{
    private const TIMEOUT_SECONDS = 10;

    /**
     * @return array<string, mixed>
     */
    public function getJson(string $url): array
    {
        return $this->send($url, null);
    }

    /**
     * @param array<string, string> $form
     * @return array<string, mixed>
     */
    public function postForm(string $url, array $form): array
    {
        return $this->send($url, http_build_query($form));
    }

    /**
     * @return array<string, mixed>
     */
    private function send(string $url, ?string $body): array
    {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new RuntimeException('Could not initialise an HTTP request.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POST, true);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if (!is_string($response)) {
            // The URL is safe to report; the request body is not, and is never included.
            throw new RuntimeException(sprintf('Request to %s failed: %s', $url, $error));
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf('Request to %s returned HTTP %d.', $url, $status));
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('Response from %s was not a JSON object.', $url));
        }

        return $decoded;
    }
}
