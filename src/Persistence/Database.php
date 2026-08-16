<?php

declare(strict_types=1);

namespace App\Persistence;

use App\Config\Config;
use PDO;
use Throwable;

final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private readonly Config $config)
    {
    }

    /**
     * The connection is opened on first use. Requests that never reach the database
     * — the login redirect, a 404 — do not pay for one.
     */
    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        return $this->pdo = new PDO(
            $this->config->databaseDsn,
            $this->config->databaseUser,
            $this->config->databasePassword,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Real prepared statements, so values are never interpolated into SQL.
                PDO::ATTR_EMULATE_PREPARES => false,
                // The server runs in Central European time while the application works
                // in UTC. Without this, NOW() would return a value in the wrong zone
                // (docs/04-datovy-model.md, section 3).
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
            ],
        );
    }

    /**
     * Registering a certificate writes to four tables. Either all of it happens or
     * none of it: a certificate without its initial check would be a record the
     * check history cannot explain.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function transactional(callable $operation): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $result = $operation();
            $pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            $pdo->rollBack();

            throw $exception;
        }
    }
}
