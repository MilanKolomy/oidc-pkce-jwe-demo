<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Certificate\ValidityStatus;
use App\Exception\NotFoundException;
use DateTimeImmutable;

/**
 * History of validity checks. Owned data, reached only through its certificate.
 */
final class CertificateCheckRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * Written as INSERT ... SELECT so the owner is part of the statement itself: a
     * check cannot be appended to someone else's certificate even if the caller
     * forgets to verify ownership first (ADR-0006). Nothing matched means the
     * certificate does not exist or belongs to another user — indistinguishable on
     * purpose.
     *
     * @throws NotFoundException
     */
    public function insertForOwnedCertificate(
        int $certificateId,
        int $userId,
        ValidityStatus $result,
        ?string $detail,
        DateTimeImmutable $checkedAt,
    ): int {
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO oidc_certificate_check (certificate_id, checked_at, result, detail)
             SELECT c.id, ?, ?, ?
               FROM oidc_certificate c
              WHERE c.id = ? AND c.user_id = ?'
        );
        $statement->execute([
            $checkedAt->format('Y-m-d H:i:s'),
            $result->value,
            $detail,
            $certificateId,
            $userId,
        ]);

        if ($statement->rowCount() === 0) {
            throw new NotFoundException();
        }

        return (int) $this->database->pdo()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllForOwnedCertificate(int $certificateId, int $userId): array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT k.id, k.checked_at, k.result, k.detail
               FROM oidc_certificate_check k
               JOIN oidc_certificate c ON c.id = k.certificate_id
              WHERE c.id = ? AND c.user_id = ?
              ORDER BY k.checked_at DESC, k.id DESC'
        );
        $statement->execute([$certificateId, $userId]);

        return $statement->fetchAll();
    }
}
