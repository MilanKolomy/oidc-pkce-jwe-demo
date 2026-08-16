<?php

declare(strict_types=1);

namespace App\Log;

/**
 * Appends to a plain text file outside the docroot.
 *
 * Production has no access to the server's error stream (OMZ-04), so a failure
 * there would otherwise be a blank page with no way to find out why. Every line
 * carries the correlation identifier that the 500 response reports back, which is
 * what makes the entry findable.
 *
 * Never pass a token, a code_verifier, a password or a key into a message or
 * context. The log is the one place where such a value would survive.
 */
final class Logger
{
    public function __construct(
        private readonly string $file,
        private readonly string $correlationId,
    ) {
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    /**
     * @param array<string, scalar|null> $context
     */
    private function write(string $level, string $message, array $context): void
    {
        $line = sprintf(
            "%s %-7s [%s] %s%s\n",
            gmdate('Y-m-d\TH:i:s\Z'),
            $level,
            $this->correlationId,
            $message,
            $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        $directory = dirname($this->file);

        if (!is_dir($directory)) {
            @mkdir($directory, 0o775, true);
        }

        // Failing to log must not fail the request; there is nowhere better to report it to.
        @file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
    }
}
