<?php

declare(strict_types=1);

namespace App\Persistence;

use DateTimeImmutable;

/**
 * Identity data. A user only ever reads their own row.
 */
final class UserRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * Named for where the identifier comes from: the sub claim of the decrypted
     * application token, never a value taken from the request. That is why this is
     * not the "find by identifier" that ADR-0006 rules out for owned data, and the
     * name says so rather than leaving it to be remembered.
     *
     * @return array<string, mixed>|null
     */
    public function findByTokenSubject(int $id): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT id, google_sub, email, display_name, created_at, last_login_at
               FROM oidc_user
              WHERE id = ?'
        );
        $statement->execute([$id]);

        return $statement->fetch() ?: null;
    }

    /**
     * Pairing is on the provider's subject identifier, never on the e-mail address:
     * the address can change while sub cannot (FR-02).
     *
     * @return array<string, mixed>|null
     */
    public function findByGoogleSub(string $googleSub): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT id, google_sub, email, display_name, created_at, last_login_at
               FROM oidc_user
              WHERE google_sub = ?'
        );
        $statement->execute([$googleSub]);

        return $statement->fetch() ?: null;
    }

    public function insert(
        string $googleSub,
        string $email,
        ?string $displayName,
        DateTimeImmutable $now,
    ): int {
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO oidc_user (google_sub, email, display_name, created_at, last_login_at)
                  VALUES (?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $googleSub,
            $email,
            $displayName,
            $now->format('Y-m-d H:i:s'),
            $now->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->database->pdo()->lastInsertId();
    }

    /**
     * The profile is refreshed on every login: the address or the display name may
     * have changed at the provider since last time.
     */
    public function updateOnLogin(int $id, string $email, ?string $displayName, DateTimeImmutable $now): void
    {
        $statement = $this->database->pdo()->prepare(
            'UPDATE oidc_user
                SET email = ?, display_name = ?, last_login_at = ?
              WHERE id = ?'
        );
        $statement->execute([$email, $displayName, $now->format('Y-m-d H:i:s'), $id]);
    }
}
