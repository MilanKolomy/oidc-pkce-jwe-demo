<?php

declare(strict_types=1);

namespace App\Certificate;

use DateTimeImmutable;

/**
 * Attributes extracted from a PEM certificate.
 *
 * Holds only what the certificate itself states. The owner, the issuing authority
 * row and the time of registration are assigned by the application, not read from
 * the input, so they are not part of this object.
 */
final class ParsedCertificate
{
    /**
     * @param list<string> $keyUsageCodes
     */
    public function __construct(
        public readonly string $subjectDn,
        public readonly string $commonName,
        public readonly string $issuerDn,
        public readonly string $issuerCommonName,
        public readonly string $serialNumber,
        public readonly DateTimeImmutable $validFrom,
        public readonly DateTimeImmutable $validTo,
        public readonly string $fingerprintSha256,
        public readonly ?string $signatureAlgorithm,
        public readonly ?string $publicKeyAlgorithm,
        public readonly ?int $publicKeyBits,
        public readonly array $keyUsageCodes,
        public readonly string $pem,
    ) {
    }
}
