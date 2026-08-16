<?php

declare(strict_types=1);

/**
 * Applies database/schema.sql to the configured database.
 *
 * Development only, and only from the command line. Production has no shell
 * access (OMZ-02), so the schema is applied there by hand through phpMyAdmin —
 * which is why this script deliberately has no web entry point.
 *
 * Usage: php database/migrate.php
 */

use App\Config\Config;
use App\Persistence\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);

$config = Config::load($root . '/.env');
$schema = file_get_contents($root . '/database/schema.sql');

if ($schema === false) {
    fwrite(STDERR, "database/schema.sql could not be read.\n");
    exit(1);
}

$pdo = (new Database($config))->pdo();
$pdo->exec($schema);

printf("Schema applied to %s.\n", $config->databaseDsn);

foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    printf("  %-32s %d row(s)\n", $table, (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn());
}
