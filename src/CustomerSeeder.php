<?php

declare(strict_types=1);

namespace Lab;

use PDO;
use Throwable;

// Adds 10,000 fake customers with Faker. Rows that already exist are not changed
final class CustomerSeeder
{
    private const TOTAL = 10000;
    private const ELIGIBLE = 8000;

    public function __construct(private PDO $db, private \Faker\Generator $faker) {}

    public function seed(): void
    {
        // The "do nothing" update keeps old rows as they are
        // We do not use INSERT IGNORE because it would also hide real errors
        $insert = $this->db->prepare(
            'INSERT INTO customers (customer_id, full_name, email, marketing_opt_in, created_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE customer_id = customer_id'
        );

        $inserted = 0;
        $existing = 0;

        $this->db->beginTransaction();
        try {
            for ($i = 1; $i <= self::TOTAL; $i++) {
                $customerId = sprintf('CUST-%05d', $i);
                $email = sprintf('customer%05d@example.com', $i);

                // TODO S1: get a fake name from Faker
                $name = $this->faker->name();

                // TODO S2: first 8000 customers say yes to marketing, the last 2000 say no
                $consent = $i <= self::ELIGIBLE ? 1 : 0;

                $insert->execute([$customerId, $name, $email, $consent, gmdate('Y-m-d H:i:s')]);

                if ($insert->rowCount() === 1) {
                    $inserted++;
                } else {
                    $existing++;
                }
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $total = (int) $this->db->query('SELECT COUNT(*) FROM customers')->fetchColumn();

        echo "Inserted: {$inserted}" . PHP_EOL;
        echo "Already existed: {$existing}" . PHP_EOL;
        echo "Total customers: {$total}" . PHP_EOL;
    }
}
