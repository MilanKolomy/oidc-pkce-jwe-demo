<?php

declare(strict_types=1);

namespace App\Oidc;

use RuntimeException;

/**
 * Reads the provider's endpoints from its discovery document rather than hardcoding
 * them, so a change on Google's side does not require a release.
 *
 * Deliberately not cached. docs/03-navrh-reseni.md asks for caching of the key set,
 * not of this document, and a cache is a thing that can go stale and be wrong.
 */
final class Discovery
{
    private const DOCUMENT_URL = 'https://accounts.google.com/.well-known/openid-configuration';

    /** @var array<string, mixed>|null */
    private ?array $document = null;

    public function __construct(private readonly HttpClient $client)
    {
    }

    public function issuer(): string
    {
        return $this->value('issuer');
    }

    public function authorizationEndpoint(): string
    {
        return $this->value('authorization_endpoint');
    }

    public function tokenEndpoint(): string
    {
        return $this->value('token_endpoint');
    }

    public function jwksUri(): string
    {
        return $this->value('jwks_uri');
    }

    private function value(string $key): string
    {
        $this->document ??= $this->client->getJson(self::DOCUMENT_URL);
        $value = $this->document[$key] ?? null;

        if (!is_string($value) || $value === '') {
            throw new RuntimeException(sprintf('Discovery document has no usable "%s".', $key));
        }

        return $value;
    }
}
