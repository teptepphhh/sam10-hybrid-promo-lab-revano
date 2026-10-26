<?php

declare(strict_types=1);

namespace Lab;

use Aws\Sqs\SqsClient;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Publishes outbox rows to SQS. Payloads carry identifiers and content only, never names or emails.
 */
final class QueuePublisher
{
    public function __construct(
        private PDO $db,
        private SqsClient $sqs,
        private string $queueUrl,
        private ActivityLogger $logger,
    ) {}

    public function publishPending(): void
    {
        $select = $this->db->prepare(
            "SELECT request_id, campaign_id, customer_id, promo_code, subject, body
               FROM marketing_outbox
              WHERE status = 'PENDING'
              ORDER BY created_at, request_id"
        );
        $select->execute();
        $rows = $select->fetchAll();

        if ($rows === []) {
            echo "No PENDING outbox rows to publish." . PHP_EOL;
            return;
        }

        $update = $this->db->prepare(
            "UPDATE marketing_outbox
                SET status = 'QUEUED', queued_at = ?, sqs_message_id = ?
              WHERE request_id = ? AND status = 'PENDING'"
        );

        $published = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $payload = $this->payloadFromRow($row);
            $ids = $this->ids($payload);

            try {
                // TODO Q1 (completed): send the JSON payload to SQS.
                $result = $this->sqs->sendMessage([
                    'QueueUrl'    => $this->queueUrl,
                    'MessageBody' => json_encode($payload, JSON_THROW_ON_ERROR),
                ]);
                $messageId = (string) $result['MessageId'];

                // TODO Q2 (completed): mark the row QUEUED only after SQS accepted the message.
                $update->execute([gmdate('Y-m-d H:i:s'), $messageId, $row['request_id']]);

                // TODO M1 (completed): log the queued event with the SQS MessageId.
                $this->logger->record('queued', $ids + ['messageId' => $messageId]);
                echo "queued    {$row['request_id']} {$row['customer_id']} -> {$messageId}" . PHP_EOL;
                $published++;
            } catch (Throwable $e) {
                // The row stays PENDING so the next run retries it.
                $this->logger->record('enqueue_failed', $ids + ['error' => $e->getMessage()]);
                echo "FAILED    {$row['request_id']} {$row['customer_id']}: {$e->getMessage()}" . PHP_EOL;
                $failed++;
            }
        }

        echo PHP_EOL . "Published: {$published}, failed: {$failed}, total pending found: " . count($rows) . PHP_EOL;
    }

    // Re-sends the existing outbox payload without changing the outbox row (sequential-duplicate test).
    public function resubmit(string $campaignId, string $customerId): void
    {
        $select = $this->db->prepare(
            'SELECT request_id, campaign_id, customer_id, promo_code, subject, body
               FROM marketing_outbox
              WHERE campaign_id = ? AND customer_id = ?'
        );
        $select->execute([$campaignId, $customerId]);
        $row = $select->fetch();

        if ($row === false) {
            throw new RuntimeException("No outbox row found for {$campaignId} / {$customerId}");
        }

        $payload = $this->payloadFromRow($row);
        $messageId = $this->send($payload);

        $this->logger->record('resubmitted', $this->ids($payload) + ['messageId' => $messageId]);
        echo "Resubmitted {$payload['requestId']} ({$campaignId} / {$customerId}) -> {$messageId}" . PHP_EOL;
    }

    // Sends one corrected payload with a fresh requestId, without touching the outbox.
    public function publishDiagnostic(string $campaignId, string $customerId, string $promoCode): void
    {
        $payload = [
            'requestId'  => bin2hex(random_bytes(16)),
            'campaignId' => $campaignId,
            'customerId' => $customerId,
            'promoCode'  => $promoCode,
            'subject'    => 'Recovery test',
            'body'       => 'Diagnostic recovery request.',
        ];

        $messageId = $this->send($payload);

        $this->logger->record('diagnostic_queued', $this->ids($payload) + ['messageId' => $messageId]);
        echo "Diagnostic message queued -> {$messageId}" . PHP_EOL;
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    }

    private function send(array $payload): string
    {
        $result = $this->sqs->sendMessage([
            'QueueUrl'    => $this->queueUrl,
            'MessageBody' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        return (string) $result['MessageId'];
    }

    private function payloadFromRow(array $row): array
    {
        return [
            'requestId'  => $row['request_id'],
            'campaignId' => $row['campaign_id'],
            'customerId' => $row['customer_id'],
            'promoCode'  => $row['promo_code'],
            'subject'    => $row['subject'],
            'body'       => $row['body'],
        ];
    }

    private function ids(array $payload): array
    {
        return [
            'requestId'  => $payload['requestId'],
            'campaignId' => $payload['campaignId'],
            'customerId' => $payload['customerId'],
        ];
    }
}
