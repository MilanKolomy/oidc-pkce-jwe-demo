<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    /**
     * @param array<string, string> $query
     * @param array<string, string> $cookies
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $cookies,
        public readonly string $body,
    ) {
    }

    public static function fromGlobals(): self
    {
        $target = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) (parse_url($target, PHP_URL_PATH) ?: '/');

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            '/' . trim(rawurldecode($path), '/'),
            array_map('strval', $_GET),
            array_map('strval', $_COOKIE),
            (string) file_get_contents('php://input'),
        );
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    public function isApi(): bool
    {
        return str_starts_with($this->path, '/api/');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonBody(): array
    {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : [];
    }
}
