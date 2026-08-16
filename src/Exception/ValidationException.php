<?php

declare(strict_types=1);

namespace App\Exception;

final class ValidationException extends HttpException
{
    public function __construct(string $detail = '', ?string $logReason = null)
    {
        parent::__construct(422, 'validation-failed', 'Validation failed', $detail, $logReason);
    }
}
