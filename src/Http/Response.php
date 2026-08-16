<?php

declare(strict_types=1);

namespace App\Http;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    private function __construct(
        private readonly int $status,
        private readonly array $headers,
        private readonly string $body,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        return new self(
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'] + $headers,
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public static function yaml(string $yaml): self
    {
        return new self(200, ['Content-Type' => 'application/yaml; charset=utf-8'], $yaml);
    }

    public static function redirect(string $url): self
    {
        return new self(302, ['Location' => $url], '');
    }

    /**
     * @param array<string, mixed> $problem
     */
    public static function problem(array $problem, int $status): self
    {
        return new self(
            $status,
            ['Content-Type' => 'application/problem+json; charset=utf-8'],
            json_encode($problem, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        );
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}
