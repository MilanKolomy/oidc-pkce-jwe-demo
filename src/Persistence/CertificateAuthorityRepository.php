<?php

declare(strict_types=1);

namespace App\Persistence;

use DateTimeImmutable;
use PDO;

/**
 * Shared lookup data: authorities have no owner and are readable by every
 * authenticated user. Binding them to whoever registered the first certificate
 * would produce duplicates and defeat the point of a lookup table
 * (docs/03-navrh-reseni.md, section 5).
 */
final class CertificateAuthorityRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * Created on first occurrence of an issuer, paired on the distinguished name.
     *
     * Written as an upsert rather than select-then-insert so that two requests
     * registering certificates from the same new authority cannot race into a
     * duplicate key error. LAST_INSERT_ID(id) makes the existing row report its own
     * identifier, at the cost of consuming an auto-increment value on a hit.
     */
    public function findOrCreate(string $subjectDn, string $commonName, DateTimeImmutable $now): int
    {
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO oidc_certificate_authority (subject_dn, common_name, created_at)
                  VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
        );
        $statement->execute([$subjectDn, $commonName, $now->format('Y-m-d H:i:s')]);

        return (int) $this->database->pdo()->lastInsertId();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(int $page, int $perPage): array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT id, subject_dn, common_name
               FROM oidc_certificate_authority
              ORDER BY common_name, id
              LIMIT :limit OFFSET :offset'
        );
        // Bound as integers on purpose: LIMIT rejects a string, and with emulation
        // switched off PDO would otherwise send these as strings.
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function count(): int
    {
        return (int) $this->database->pdo()
            ->query('SELECT COUNT(*) FROM oidc_certificate_authority')
            ->fetchColumn();
    }
}
