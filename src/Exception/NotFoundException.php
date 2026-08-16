<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Also thrown for records owned by another user: the two cases are deliberately
 * indistinguishable, because 403 would confirm that the record exists (ADR-0006).
 */
final class NotFoundException extends HttpException
{
    public function __construct(string $detail = '', ?string $logReason = null)
    {
        parent::__construct(404, 'not-found', 'Not found', $detail, $logReason);
    }
}
