<?php

declare(strict_types=1);

namespace App\Exception;

final class BadRequestException extends HttpException
{
    public function __construct(string $detail = '')
    {
        parent::__construct(400, 'bad-request', 'Bad request', $detail);
    }
}
