<?php

declare(strict_types=1);

namespace Lab;

use PDO;
use RuntimeException;
use Throwable;

// Picks 50 random opted-in customers and saves a PENDING outbox row for each
// Only uses the local database, no SQS and no email here
final class CampaignPreparer
{
    private const RECIPIENTS = 50;

    public function __construct(private PDO $db, private ActivityLogger $logger) {}

    public function prepare(string $campaignId, string $promoCode, string $subject = 'Laboratory promotion'): void
    {
        $this->db->beginTransaction();
        try {
            // TODO C3: count the rows first. 50 means already done, so keep them. Any other number is an error
            // FOR UPDATE locks these rows so two runs at the same time cannot both pick customers
            $count = $this->db->prepare('SELECT COUNT(*) FROM marketing_outbox WHERE campaign_id = ? FOR UPDATE');
            $count->execute([$campaignId]);
            $existing = (int) $count->fetchColumn();

            if ($existing === self::RECIPIENTS) {
                $this->db->rollBack();
                echo "Campaign already prepared; original 50 recipients preserved" . PHP_EOL;
                return;
            }
            if ($existing > 0) {
                throw new RuntimeException(
                    "Campaign {$campaignId} is inconsistent: {$existing} outbox rows exist, expected 0 or " . self::RECIPIENTS
                );
            }

            // TODO C2: pick 50 different opted-in customers at random
            $select = $this->db->prepare(
                'SELECT customer_id FROM customers WHERE marketing_opt_in = 1 ORDER BY RAND() LIMIT ' . self::RECIPIENTS
            );
            $select->execute();
            $customerIds = $select->fetchAll(PDO::FETCH_COLUMN);

            if (count($customerIds) < self::RECIPIENTS) {
                throw new RuntimeException(
                    'Only ' . count($customerIds) . ' eligible customers found; ' . self::RECIPIENTS . ' are required'
                );
            }

            $body = "You have been selected to receive our promotional offer. Use promo code {$promoCode} at checkout.";

            $insert = $this->db->prepare(
                'INSERT INTO marketing_outbox (request_id, campaign_id, customer_id, promo_code, subject, body, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            foreach ($customerIds as $customerId) {
                $requestId = bin2hex(random_bytes(16));

                // TODO C1: save the outbox row. status is PENDING by default
                $insert->execute([
                    $requestId,
                    $campaignId,
                    $customerId,
                    $promoCode,
                    $subject,
                    $body,
                    gmdate('Y-m-d H:i:s'),
                ]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $this->logger->record('campaign_prepared', ['campaignId' => $campaignId, 'count' => self::RECIPIENTS]);

        echo "Campaign {$campaignId} prepared" . PHP_EOL;
        echo "  Recipients: " . self::RECIPIENTS . " (status PENDING)" . PHP_EOL;
        echo "  Promo code: {$promoCode}" . PHP_EOL;
        echo "  Subject: {$subject}" . PHP_EOL;
    }
}
