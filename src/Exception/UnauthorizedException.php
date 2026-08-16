<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Returned before the existence of any requested record is evaluated, so an
 * unauthenticated caller learns nothing about what exists (ADR-0006, FR-15).
 */
final class UnauthorizedException extends HttpException
{
    public function __construct(
        string $detail = 'Session token is missing or expired.',
        ?string $logReason = null,
    ) {
        parent::__construct(401, 'unauthorized', 'Authentication required', $detail, $logReason);
    }
}
