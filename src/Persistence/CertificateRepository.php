<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Certificate\ParsedCertificate;
use App\Certificate\ValidityStatus;
use App\Exception\ConflictException;
use DateTimeImmutable;
use PDO;
use PDOException;

/**
 * Owned data. There is deliberately no "find by identifier": every read takes the
 * owner as well, so a request for someone else's certificate returns nothing and
 * becomes a 404 by itself, rather than by a check somebody has to remember to
 * write (ADR-0006).
 */
final class CertificateRepository
{
    private const SELECT_SUMMARY = <<<'SQL'
        SELECT c.id,
               c.common_name,
               c.serial_number,
               c.valid_from,
               c.valid_to,
               a.id          AS authority_id,
               a.common_name AS authority_common_name,
               a.subject_dn  AS authority_subject_dn
          FROM oidc_certificate c
          JOIN oidc_certificate_authority a ON a.id = c.authority_id
        SQL;

    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllForOwner(
        int $userId,
        ?ValidityStatus $status,
        int $page,
        int $perPage,
        DateTimeImmutable $now,
    ): array {
        [$condition, $parameters] = $this->statusCondition($status, $now);

        $statement = $this->database->pdo()->prepare(
            self::SELECT_SUMMARY . '
              WHERE c.user_id = :userId' . $condition . '
              ORDER BY c.created_at DESC, c.id DESC
              LIMIT :limit OFFSET :offset'
        );

        $statement->bindValue(':userId', $userId, PDO::PARAM_INT);

        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value);
        }

        // Integers on purpose: LIMIT rejects a string, and with emulation switched
        // off PDO would otherwise send these as strings.
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function countForOwner(int $userId, ?ValidityStatus $status, DateTimeImmutable $now): int
    {
        [$condition, $parameters] = $this->statusCondition($status, $now);

        $statement = $this->database->pdo()->prepare(
            'SELECT COUNT(*) FROM oidc_certificate c WHERE c.user_id = :userId' . $condition
        );
        $statement->execute(['userId' => $userId] + $parameters);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForOwner(int $id, int $userId): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT c.id,
                    c.common_name,
                    c.subject_dn,
                    c.serial_number,
                    c.valid_from,
                    c.valid_to,
                    c.fingerprint_sha256,
                    c.signature_algorithm,
                    c.public_key_algorithm,
                    c.public_key_bits,
                    c.created_at,
                    a.id          AS authority_id,
                    a.common_name AS authority_common_name,
                    a.subject_dn  AS authority_subject_dn
               FROM oidc_certificate c
               JOIN oidc_certificate_authority a ON a.id = c.authority_id
              WHERE c.id = ? AND c.user_id = ?'
        );
        $statement->execute([$id, $userId]);

        return $statement->fetch() ?: null;
    }

    /**
     * @throws ConflictException when this user has already registered this certificate
     */
    public function insert(
        int $userId,
        int $authorityId,
        ParsedCertificate $certificate,
        DateTimeImmutable $now,
    ): int {
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO oidc_certificate (
                        user_id, authority_id, subject_dn, common_name, serial_number,
                        valid_from, valid_to, fingerprint_sha256, signature_algorithm,
                        public_key_algorithm, public_key_bits, pem, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        try {
            $statement->execute([
                $userId,
                $authorityId,
                $certificate->subjectDn,
                $certificate->commonName,
                $certificate->serialNumber,
                $certificate->validFrom->format('Y-m-d H:i:s'),
                $certificate->validTo->format('Y-m-d H:i:s'),
                $certificate->fingerprintSha256,
                $certificate->signatureAlgorithm,
                $certificate->publicKeyAlgorithm,
                $certificate->publicKeyBits,
                $certificate->pem,
                $now->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            // Uniqueness is per owner, not global: the same public certificate may
            // legitimately be registered by several users. Caught rather than checked
            // beforehand, because a check followed by an insert can still lose a race.
            if ($this->isDuplicate($exception)) {
                throw new ConflictException('This certificate is already registered.');
            }

            throw $exception;
        }

        return (int) $this->database->pdo()->lastInsertId();
    }

    private function isDuplicate(PDOException $exception): bool
    {
        return ($exception->errorInfo[1] ?? null) === 1062;
    }

    /**
     * Status is derived from the validity period, not stored, so it has to be
     * evaluated in SQL — filtering in PHP would page over the wrong set.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function statusCondition(?ValidityStatus $status, DateTimeImmutable $now): array
    {
        if ($status === null) {
            return ['', []];
        }

        $moment = $now->format('Y-m-d H:i:s');

        // Each placeholder is named once. Whether PDO accepts the same named
        // placeholder twice with emulation switched off differs between versions,
        // and this is not worth depending on.
        return match ($status) {
            ValidityStatus::Valid => [
                ' AND c.valid_from <= :notAfter AND c.valid_to >= :notBefore',
                ['notAfter' => $moment, 'notBefore' => $moment],
            ],
            ValidityStatus::NotYetValid => [' AND c.valid_from > :now', ['now' => $moment]],
            ValidityStatus::Expired => [' AND c.valid_to < :now', ['now' => $moment]],
        };
    }
}
