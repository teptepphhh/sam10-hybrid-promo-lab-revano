<?php

declare(strict_types=1);

namespace Lab;

use PDO;
use RuntimeException;

// Creates the tables and prints a summary without names or emails
final class LocalDatabaseTools
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    // Run schema.sql (TODO D1) one statement at a time, then list the tables
    public function initialize(string $schemaPath): void
    {
        $sql = @file_get_contents($schemaPath);
        if ($sql === false) {
            throw new RuntimeException("Unable to read schema file: {$schemaPath}");
        }

        foreach ($this->splitStatements($sql) as $statement) {
            $this->pdo->exec($statement);
        }

        echo "Schema applied from " . basename($schemaPath) . PHP_EOL;
        echo "Tables in database:" . PHP_EOL;

        $tables = $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            echo "  - {$table}" . PHP_EOL;
        }
    }

    // Only counts and IDs here, no names or emails
    public function display(): void
    {
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
        echo "Total customers: {$total}" . PHP_EOL . PHP_EOL;

        echo "Customers by marketing_opt_in:" . PHP_EOL;
        $optIn = $this->pdo->query(
            'SELECT marketing_opt_in, COUNT(*) AS total FROM customers GROUP BY marketing_opt_in ORDER BY marketing_opt_in'
        )->fetchAll();
        $counts = [0 => 0, 1 => 0];
        foreach ($optIn as $row) {
            $counts[(int) $row['marketing_opt_in']] = (int) $row['total'];
        }
        echo "  opt_in=0: {$counts[0]}" . PHP_EOL;
        echo "  opt_in=1: {$counts[1]}" . PHP_EOL . PHP_EOL;

        echo "Outbox by campaign and status:" . PHP_EOL;
        $this->printGroupedCounts(
            'SELECT campaign_id, status, COUNT(*) AS total FROM marketing_outbox GROUP BY campaign_id, status ORDER BY campaign_id, status'
        );

        echo "History by campaign and status:" . PHP_EOL;
        $this->printGroupedCounts(
            'SELECT campaign_id, status, COUNT(*) AS total FROM sent_marketing_emails GROUP BY campaign_id, status ORDER BY campaign_id, status'
        );

        echo "5 most recent outbox rows:" . PHP_EOL;
        $statement = $this->pdo->prepare(
            'SELECT request_id, campaign_id, customer_id, status, sqs_message_id
               FROM marketing_outbox
              ORDER BY created_at DESC, request_id DESC
              LIMIT :limit'
        );
        $statement->bindValue(':limit', 5, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll();

        if ($rows === []) {
            echo "  (none)" . PHP_EOL;
        }
        foreach ($rows as $row) {
            printf(
                "  %s | %s | %s | %s | %s%s",
                $row['request_id'],
                $row['campaign_id'],
                $row['customer_id'],
                $row['status'],
                $row['sqs_message_id'] ?? '-',
                PHP_EOL
            );
        }
    }

    private function printGroupedCounts(string $sql): void
    {
        $rows = $this->pdo->query($sql)->fetchAll();

        if ($rows === []) {
            echo "  (none)" . PHP_EOL . PHP_EOL;
            return;
        }
        foreach ($rows as $row) {
            echo "  {$row['campaign_id']} | {$row['status']}: {$row['total']}" . PHP_EOL;
        }
        echo PHP_EOL;
    }

    // Split the SQL file on ";" and skip "--" comment lines and empty parts
    private function splitStatements(string $sql): array
    {
        $lines = array_filter(
            preg_split('/\R/', $sql),
            static fn (string $line): bool => !str_starts_with(ltrim($line), '--')
        );

        $statements = array_map('trim', explode(';', implode("\n", $lines)));

        return array_values(array_filter($statements, static fn (string $s): bool => $s !== ''));
    }
}
