<?php

declare(strict_types=1);

namespace App\Persistence;

/**
 * Shared lookup data, like authorities. The standard values are seeded by
 * database/schema.sql; anything else encountered in a certificate is added on
 * first sight with the code doubling as the label.
 */
final class KeyUsageRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function findOrCreate(string $code, string $label): int
    {
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO oidc_key_usage (code, label)
                  VALUES (?, ?)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
        );
        $statement->execute([$code, $label]);

        return (int) $this->database->pdo()->lastInsertId();
    }

    public function link(int $certificateId, int $keyUsageId): void
    {
        $statement = $this->database->pdo()->prepare(
            'INSERT IGNORE INTO oidc_certificate_key_usage (certificate_id, key_usage_id)
                  VALUES (?, ?)'
        );
        $statement->execute([$certificateId, $keyUsageId]);
    }

    /**
     * Reached through the certificate and filtered by owner in the same query, so
     * the link table cannot be used to read the key usages of someone else's
     * certificate (ADR-0006).
     *
     * @return list<array<string, mixed>>
     */
    public function findAllForOwnedCertificate(int $certificateId, int $userId): array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT u.code, u.label
               FROM oidc_key_usage u
               JOIN oidc_certificate_key_usage l ON l.key_usage_id = u.id
               JOIN oidc_certificate c ON c.id = l.certificate_id
              WHERE c.id = ? AND c.user_id = ?
              ORDER BY u.id'
        );
        $statement->execute([$certificateId, $userId]);

        return $statement->fetchAll();
    }
}
