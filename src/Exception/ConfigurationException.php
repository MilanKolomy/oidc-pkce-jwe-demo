<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/**
 * Thrown during startup, before a request can be served. Never carries a
 * configuration value in its message — only the name of what is missing.
 */
final class ConfigurationException extends RuntimeException
{
}
