<?php

declare(strict_types=1);

namespace App\Http;

use App\Exception\NotFoundException;

/**
 * Branching by path and method, as decided in ADR-0005. No framework, no
 * configuration format, no route caching — the whole application has eleven routes.
 */
final class Router
{
    /**
     * @var list<array{method: string, regex: string, handler: callable}>
     */
    private array $routes = [];

    /**
     * Placeholders are written as {name} and match a single path segment. The matched
     * values are passed to the handler as the second argument.
     *
     * @param callable(Request, array<string, string>): Response $handler
     */
    public function add(string $method, string $pattern, callable $handler): void
    {
        $regex = preg_replace('~\{([a-zA-Z]+)\}~', '(?P<$1>[^/]+)', $pattern);

        $this->routes[] = [
            'method' => strtoupper($method),
            'regex' => '~^' . $regex . '$~',
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }

            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }

            $parameters = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            return ($route['handler'])($request, $parameters);
        }

        throw new NotFoundException();
    }
}
