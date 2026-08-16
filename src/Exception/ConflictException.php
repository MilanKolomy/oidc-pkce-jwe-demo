<?php

declare(strict_types=1);

namespace App\Exception;

final class ConflictException extends HttpException
{
    public function __construct(string $detail = '')
    {
        parent::__construct(409, 'conflict', 'Conflict', $detail);
    }
}
