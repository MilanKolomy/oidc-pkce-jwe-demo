<?php

declare(strict_types=1);

namespace App\Http;

use App\Exception\HttpException;

/**
 * Error representation following RFC 7807, as defined in docs/openapi.yaml.
 */
final class Problem
{
    /**
     * Problem types are stable identifiers, not addresses to fetch. They are therefore
     * a fixed constant rather than something UrlBuilder composes — otherwise the same
     * problem would be identified differently in development and in production.
     */
    private const TYPE_BASE = 'https://monet.super-web.cz/problems/';

    public static function fromException(HttpException $exception, string $instance): Response
    {
        return self::build(
            $exception->status(),
            $exception->problemType(),
            $exception->title(),
            $exception->detail(),
            $instance,
        );
    }

    /**
     * Response to an unexpected failure. Carries a correlation identifier instead of
     * the cause: the details belong in the application log, not in the response (OMZ-04).
     */
    public static function internal(string $instance, string $correlationId, ?string $detail = null): Response
    {
        return self::build(
            500,
            'internal-error',
            'Internal server error',
            $detail,
            $instance,
            $correlationId,
        );
    }

    private static function build(
        int $status,
        string $type,
        string $title,
        ?string $detail,
        string $instance,
        ?string $correlationId = null,
    ): Response {
        $problem = [
            'type' => self::TYPE_BASE . $type,
            'title' => $title,
            'status' => $status,
        ];

        if ($detail !== null && $detail !== '') {
            $problem['detail'] = $detail;
        }

        $problem['instance'] = $instance;

        if ($correlationId !== null) {
            $problem['correlationId'] = $correlationId;
        }

        return Response::problem($problem, $status);
    }
}
