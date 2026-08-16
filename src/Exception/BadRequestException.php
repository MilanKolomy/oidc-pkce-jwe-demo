<?php

declare(strict_types=1);

namespace App\Exception;

final class BadRequestException extends HttpException
{
    public function __construct(string $detail = '', ?string $logReason = null)
    {
        parent::__construct(400, 'bad-request', 'Bad request', $detail, $logReason);
    }
}
