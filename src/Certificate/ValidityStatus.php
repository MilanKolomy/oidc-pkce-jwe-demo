<?php

declare(strict_types=1);

namespace App\Certificate;

/**
 * Validity evaluated against the certificate's validity period only. Revocation
 * and the trust chain are not verified.
 *
 * The database stores this as plain text rather than an ENUM, because extending an
 * ENUM means altering the table, which is impractical on hosting without shell
 * access. The set of values therefore lives here (docs/04-datovy-model.md, section 3).
 */
enum ValidityStatus: string
{
    case Valid = 'valid';
    case NotYetValid = 'not_yet_valid';
    case Expired = 'expired';
}
