<?php

declare(strict_types=1);

namespace Lab;

use PDO;

/**
 * Creates the PDO connection to the local MySQL database.
 */
final class Database
{
    // connect() builds a utf8mb4 PDO connection from environment variables and forces UTC.
    public function connect(): PDO
    {
        $host     = getenv('DB_HOST') ?: '127.0.0.1';
        $port     = getenv('DB_PORT') ?: '3306';
        $name     = getenv('DB_NAME') ?: 'marketing_lab';
        $user     = Settings::required('DB_USER');
        $password = getenv('DB_PASSWORD') ?: '';

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        $pdo->exec("SET time_zone = '+00:00'");

        return $pdo;
    }
}
