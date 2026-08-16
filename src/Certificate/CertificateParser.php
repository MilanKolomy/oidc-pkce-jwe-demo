<?php

declare(strict_types=1);

namespace App\Certificate;

use App\Exception\ValidationException;
use DateTimeImmutable;
use DateTimeZone;
use OpenSSLCertificate;

/**
 * Turns the text of a PEM certificate into its attributes.
 *
 * The certificate arrives as text in the request body rather than as an uploaded
 * file, which removes the whole question of storing and serving uploaded content
 * (docs/03-navrh-reseni.md, section 6).
 *
 * No message produced here ever contains any part of the input. Rejecting a paste
 * that turns out to hold a private key and then quoting it back in an error would
 * defeat the point of rejecting it.
 */
final class CertificateParser
{
    /**
     * Matches the request body limit in docs/openapi.yaml. Checked here so the web
     * form is bound by it too, not only the API.
     */
    private const MAX_LENGTH = 16384;

    /**
     * Any PEM label containing PRIVATE KEY: PRIVATE KEY, RSA PRIVATE KEY,
     * EC PRIVATE KEY, ENCRYPTED PRIVATE KEY, OPENSSH PRIVATE KEY and the rest.
     */
    private const PRIVATE_KEY_LABEL = '/-----BEGIN [A-Z0-9 ]*PRIVATE KEY[A-Z0-9 ]*-----/';

    /**
     * OpenSSL reports key usage in long form. The registry stores the X.509
     * technical codes, so the long names are translated back.
     */
    private const KEY_USAGE_CODES = [
        'Digital Signature' => 'digitalSignature',
        'Non Repudiation' => 'nonRepudiation',
        'Key Encipherment' => 'keyEncipherment',
        'Data Encipherment' => 'dataEncipherment',
        'Key Agreement' => 'keyAgreement',
        'Certificate Sign' => 'keyCertSign',
        'CRL Sign' => 'cRLSign',
        'Encipher Only' => 'encipherOnly',
        'Decipher Only' => 'decipherOnly',
    ];

    private const KEY_TYPES = [
        OPENSSL_KEYTYPE_RSA => 'RSA',
        OPENSSL_KEYTYPE_DSA => 'DSA',
        OPENSSL_KEYTYPE_DH => 'DH',
        OPENSSL_KEYTYPE_EC => 'EC',
    ];

    public function parse(string $pem): ParsedCertificate
    {
        $pem = trim($pem);

        if ($pem === '') {
            throw new ValidationException('No certificate was supplied.');
        }

        if (strlen($pem) > self::MAX_LENGTH) {
            throw new ValidationException(sprintf(
                'The supplied text is longer than the %d character limit.',
                self::MAX_LENGTH
            ));
        }

        // Before parsing, not after: the input must be refused while it is still
        // only a local variable.
        if (preg_match(self::PRIVATE_KEY_LABEL, $pem) === 1) {
            throw new ValidationException(
                'The supplied text contains a private key. Send only the public certificate.'
            );
        }

        // Suppressed: a malformed certificate makes OpenSSL raise a warning, which
        // would be printed into the response body.
        $certificate = @openssl_x509_read($pem);

        if (!$certificate instanceof OpenSSLCertificate) {
            throw new ValidationException('The supplied text is not a valid PEM-encoded certificate.');
        }

        // Short names (CN, O, C), which is the form docs/openapi.yaml shows and the
        // form a distinguished name is conventionally written in.
        $fields = @openssl_x509_parse($certificate, true);

        if (!is_array($fields)) {
            throw new ValidationException('The certificate could not be read.');
        }

        $fingerprint = @openssl_x509_fingerprint($certificate, 'sha256');

        if (!is_string($fingerprint)) {
            throw new ValidationException('The certificate fingerprint could not be computed.');
        }

        $subjectDn = $this->distinguishedName($fields['subject'] ?? []);
        $issuerDn = $this->distinguishedName($fields['issuer'] ?? []);

        if ($subjectDn === '' || $issuerDn === '') {
            throw new ValidationException('The certificate has no subject or no issuer.');
        }

        [$keyAlgorithm, $keyBits] = $this->publicKey($certificate);

        return new ParsedCertificate(
            subjectDn: $subjectDn,
            commonName: $this->commonName($fields['subject'] ?? [], $subjectDn),
            issuerDn: $issuerDn,
            issuerCommonName: $this->commonName($fields['issuer'] ?? [], $issuerDn),
            serialNumber: $this->serialNumber($fields),
            validFrom: $this->timestamp($fields, 'validFrom_time_t'),
            validTo: $this->timestamp($fields, 'validTo_time_t'),
            fingerprintSha256: strtolower($fingerprint),
            signatureAlgorithm: $this->text($fields['signatureTypeLN'] ?? null, 64),
            publicKeyAlgorithm: $keyAlgorithm,
            publicKeyBits: $keyBits,
            keyUsageCodes: $this->keyUsages($fields),
            pem: $pem,
        );
    }

    /**
     * Rebuilt as "CN=..., O=..., C=..." in the order OpenSSL reports the components.
     * A component may repeat, for example several OU values, in which case OpenSSL
     * returns an array for it.
     *
     * @param array<string, mixed> $components
     */
    private function distinguishedName(array $components): string
    {
        $parts = [];

        foreach ($components as $name => $value) {
            foreach (is_array($value) ? $value : [$value] as $single) {
                if (is_scalar($single) && (string) $single !== '') {
                    $parts[] = $name . '=' . (string) $single;
                }
            }
        }

        return $this->text(implode(', ', $parts), 512) ?? '';
    }

    /**
     * Falls back to the whole distinguished name: a certificate identified only by
     * its subject alternative names has no common name, and the registry needs
     * something to display.
     *
     * @param array<string, mixed> $components
     */
    private function commonName(array $components, string $fallback): string
    {
        $commonName = $components['CN'] ?? null;

        if (is_array($commonName)) {
            $commonName = $commonName[0] ?? null;
        }

        if (!is_scalar($commonName) || (string) $commonName === '') {
            return $this->text($fallback, 255) ?? '';
        }

        return $this->text((string) $commonName, 255) ?? '';
    }

    /**
     * An X.509 serial may be up to 20 bytes and would overflow an integer type, so
     * it is kept as hexadecimal text (docs/04-datovy-model.md, section 3).
     *
     * @param array<string, mixed> $fields
     */
    private function serialNumber(array $fields): string
    {
        $serial = $fields['serialNumberHex'] ?? $fields['serialNumber'] ?? null;

        if (!is_scalar($serial) || (string) $serial === '') {
            throw new ValidationException('The certificate has no serial number.');
        }

        return $this->text(strtoupper((string) $serial), 64) ?? '';
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function timestamp(array $fields, string $key): DateTimeImmutable
    {
        $value = $fields[$key] ?? null;

        if (!is_int($value)) {
            throw new ValidationException('The certificate has no readable validity period.');
        }

        return (new DateTimeImmutable('@' . $value))->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * @return array{0: string|null, 1: int|null}
     */
    private function publicKey(OpenSSLCertificate $certificate): array
    {
        $key = @openssl_pkey_get_public($certificate);

        if ($key === false) {
            // Not fatal: both columns are nullable, and a certificate whose key the
            // installed OpenSSL cannot read is still worth recording.
            return [null, null];
        }

        $details = @openssl_pkey_get_details($key);

        if (!is_array($details)) {
            return [null, null];
        }

        $bits = $details['bits'] ?? null;

        return [
            self::KEY_TYPES[$details['type'] ?? null] ?? null,
            is_int($bits) && $bits > 0 ? $bits : null,
        ];
    }

    /**
     * @param array<string, mixed> $fields
     * @return list<string>
     */
    private function keyUsages(array $fields): array
    {
        $usage = $fields['extensions']['keyUsage'] ?? null;

        if (!is_string($usage) || trim($usage) === '') {
            return [];
        }

        $codes = [];

        foreach (explode(',', $usage) as $name) {
            $name = trim($name);

            if ($name === '') {
                continue;
            }

            // An unrecognised value is kept as it stands: the registry inserts unknown
            // usages on first sight rather than discarding them (database/schema.sql).
            $codes[] = self::KEY_USAGE_CODES[$name] ?? $name;
        }

        return array_values(array_unique($codes));
    }

    /**
     * Trims to what the column holds. A certificate with an absurdly long component
     * is a reason to store less, not to refuse the certificate.
     */
    private function text(mixed $value, int $limit): ?string
    {
        if (!is_scalar($value) || (string) $value === '') {
            return null;
        }

        return mb_substr((string) $value, 0, $limit);
    }
}
