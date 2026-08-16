<?php

declare(strict_types=1);

namespace App\Certificate;

use DateTimeImmutable;

/**
 * Evaluates a certificate against a point in time.
 *
 * The result depends on when the check was run, which is why each check is recorded
 * rather than the certificate carrying a status of its own: one valid at
 * registration may be expired at the next look (FR-09).
 *
 * Only the validity period is considered. Revocation and the trust chain are not
 * verified — deliberately out of scope, and said so in the API description so that
 * nobody mistakes this for full validation.
 */
final class ValidityChecker
{
    /**
     * Takes the window rather than a ParsedCertificate, because a check is also run
     * on a certificate already in the registry, where only the stored dates are at
     * hand and re-parsing the PEM would buy nothing.
     *
     * @return array{0: ValidityStatus, 1: string} the outcome and a note for the history
     */
    public function check(DateTimeImmutable $validFrom, DateTimeImmutable $validTo, DateTimeImmutable $now): array
    {
        if ($now < $validFrom) {
            return [
                ValidityStatus::NotYetValid,
                sprintf('Becomes valid in %s.', $this->days($now, $validFrom)),
            ];
        }

        if ($now > $validTo) {
            return [
                ValidityStatus::Expired,
                sprintf('Expired %s ago.', $this->days($validTo, $now)),
            ];
        }

        return [
            ValidityStatus::Valid,
            sprintf('Expires in %s.', $this->days($now, $validTo)),
        ];
    }

    private function days(DateTimeImmutable $from, DateTimeImmutable $to): string
    {
        $days = (int) $from->diff($to)->days;

        return match ($days) {
            // "0 days" reads as a mistake rather than as a boundary being close.
            0 => 'less than a day',
            1 => '1 day',
            default => $days . ' days',
        };
    }
}
