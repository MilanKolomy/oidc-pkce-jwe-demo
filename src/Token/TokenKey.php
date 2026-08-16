<?php

declare(strict_types=1);

namespace App\Token;

use App\Exception\ConfigurationException;
use Jose\Component\Core\JWK;

/**
 * Loads the symmetric key used to encrypt the application token (ADR-0003).
 *
 * The key is generated outside the server and uploaded with the application:
 * production has no shell access and forbids the system functions that would be
 * needed to create one there (OMZ-02). See keys/README.md.
 */
final class TokenKey
{
    private const REQUIRED_BYTES = 32;

    public static function fromFile(string $path): JWK
    {
        if (!is_readable($path)) {
            throw new ConfigurationException(
                'The application token key is missing. See keys/README.md for how to create it.'
            );
        }

        $material = base64_decode(trim((string) file_get_contents($path)), true);

        // The message names the problem and never the value: a key in a log or in an
        // error message is a leaked key.
        if ($material === false) {
            throw new ConfigurationException('The application token key is not valid Base64.');
        }

        if (strlen($material) !== self::REQUIRED_BYTES) {
            throw new ConfigurationException(sprintf(
                'The application token key must be %d bytes, got %d.',
                self::REQUIRED_BYTES,
                strlen($material)
            ));
        }

        return new JWK([
            'kty' => 'oct',
            'k' => rtrim(strtr(base64_encode($material), '+/', '-_'), '='),
        ]);
    }
}
