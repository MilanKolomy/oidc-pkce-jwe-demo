<?php

declare(strict_types=1);

namespace App\Exception;

final class UnauthorizedException extends HttpException
{
    public function __construct(string $detail = 'Session token is missing or expired.')
    {
        parent::__construct(401, 'unauthorized', 'Authentication required', $detail);
    }
}
