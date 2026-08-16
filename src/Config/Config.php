<?php

declare(strict_types=1);

namespace App\Config;

use App\Exception\ConfigurationException;

/**
 * Application configuration loaded from the .env file.
 *
 * Values are validated on load, so the rest of the application can rely on them
 * being present and well formed. A missing value fails at startup with a clear
 * message rather than surfacing as a confusing error deeper in a request.
 */
final class Config
{
    private const ENVIRONMENTS = ['dev', 'prod'];

    private const REQUIRED = [
        'APP_ENV',
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
        'GOOGLE_CLIENT_ID',
        'GOOGLE_CLIENT_SECRET',
    ];

    private function __construct(
        public readonly string $environment,
        public readonly string $databaseDsn,
        public readonly string $databaseUser,
        public readonly string $databasePassword,
        public readonly string $googleClientId,
        public readonly string $googleClientSecret,
    ) {
    }

    public static function load(string $envFile): self
    {
        if (!is_readable($envFile)) {
            throw new ConfigurationException(sprintf(
                'Configuration file %s is missing or unreadable. Copy .env.example to .env and fill it in.',
                basename($envFile)
            ));
        }

        $contents = file_get_contents($envFile);

        if ($contents === false) {
            throw new ConfigurationException(sprintf('Configuration file %s could not be read.', basename($envFile)));
        }

        $values = self::parse($contents);

        $missing = array_values(array_filter(
            self::REQUIRED,
            static fn (string $key): bool => trim((string) ($values[$key] ?? '')) === ''
        ));

        if ($missing !== []) {
            throw new ConfigurationException('Missing configuration values: ' . implode(', ', $missing) . '.');
        }

        // Optional: the default port covers most installations, and PDO cannot take
        // host and port as one string.
        $port = trim((string) ($values['DB_PORT'] ?? ''));

        if ($port !== '' && preg_match('/^\d+$/', $port) !== 1) {
            throw new ConfigurationException(sprintf('DB_PORT must be a number, got "%s".', $port));
        }

        $environment = trim($values['APP_ENV']);

        if (!in_array($environment, self::ENVIRONMENTS, true)) {
            throw new ConfigurationException(sprintf(
                'APP_ENV must be one of %s, got "%s".',
                implode(', ', self::ENVIRONMENTS),
                $environment
            ));
        }

        return new self(
            $environment,
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                trim($values['DB_HOST']),
                $port === '' ? 3306 : (int) $port,
                trim($values['DB_NAME']),
            ),
            trim($values['DB_USER']),
            // The only value allowed to be empty: local MySQL installations often have none.
            (string) ($values['DB_PASSWORD'] ?? ''),
            trim($values['GOOGLE_CLIENT_ID']),
            trim($values['GOOGLE_CLIENT_SECRET']),
        );
    }

    public function isProduction(): bool
    {
        return $this->environment === 'prod';
    }

    /**
     * A value runs to the end of its line.
     *
     * parse_ini_file() is deliberately not used: it treats ; as an inline comment
     * marker even in raw mode, so a password containing one is silently truncated
     * to the part before it. The truncated value is still a non-empty string, so it
     * passes validation and surfaces much later as a failed database connection.
     * A secret is the worst possible thing to corrupt quietly.
     *
     * @return array<string, string>
     */
    private static function parse(string $contents): array
    {
        $values = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            // A comment only at the start of a line, never inside a value.
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }

            $separator = strpos($line, '=');

            if ($separator === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator));
            $value = trim(substr($line, $separator + 1));

            // Surrounding quotes are optional, and stripped when present as a pair.
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }

            $values[$key] = $value;
        }

        return $values;
    }
}
