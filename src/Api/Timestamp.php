<?php

declare(strict_types=1);

namespace App\Api;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Converts the DATETIME values stored in UTC into the date-time format the API
 * describes, in one place so that every endpoint renders them alike.
 */
final class Timestamp
{
    public static function toIso8601(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }
}
