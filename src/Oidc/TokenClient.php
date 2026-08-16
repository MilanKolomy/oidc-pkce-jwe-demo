<?php

declare(strict_types=1);

namespace App\Oidc;

use RuntimeException;

/**
 * Exchanges the authorization code for tokens, server to server.
 *
 * The access token Google returns is deliberately not read or stored: this
 * application never calls Google's APIs, and keeping a credential nobody uses would
 * be holding a permission for no reason (docs/03-navrh-reseni.md, section 3).
 */
final class TokenClient
{
    public function __construct(
        private readonly HttpClient $client,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
    }

    /**
     * @return string the raw id_token
     */
    public function exchangeCode(
        string $tokenEndpoint,
        string $code,
        string $codeVerifier,
        string $redirectUri,
    ): string {
        $response = $this->client->postForm($tokenEndpoint, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code_verifier' => $codeVerifier,
        ]);

        $idToken = $response['id_token'] ?? null;

        if (!is_string($idToken) || $idToken === '') {
            throw new RuntimeException('The token response contained no id_token.');
        }

        return $idToken;
    }
}
