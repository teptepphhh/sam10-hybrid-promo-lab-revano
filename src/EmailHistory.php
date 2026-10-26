<?php

declare(strict_types=1);

namespace Lab;

use PDO;

/**
 * Stores the latest delivery outcome per campaign/customer in sent_marketing_emails.
 */
final class EmailHistory
{
    public function __construct(private PDO $db) {}

    // A SENT or SKIPPED row is final; FAILED rows may be retried.
    public function isComplete(string $campaignId, string $customerId): bool
    {
        $statement = $this->db->prepare(
            "SELECT 1 FROM sent_marketing_emails
              WHERE campaign_id = ? AND customer_id = ? AND status IN ('SENT', 'SKIPPED')
              LIMIT 1"
        );
        $statement->execute([$campaignId, $customerId]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Upserts one row; the table keeps only the latest outcome per (campaign_id, customer_id).
     *
     * @param array{campaign_id: string, customer_id: string, request_id: string, promo_code: string,
     *              recipient_email: ?string, subject: string, status: string, provider: ?string,
     *              message_id: ?string, reason: ?string, processed_at: string, sent_at: ?string} $outcome
     */
    public function save(array $outcome): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO sent_marketing_emails
                (campaign_id, customer_id, request_id, promo_code, recipient_email, subject,
                 status, provider, message_id, reason, processed_at, sent_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                request_id      = VALUES(request_id),
                promo_code      = VALUES(promo_code),
                recipient_email = VALUES(recipient_email),
                subject         = VALUES(subject),
                status          = VALUES(status),
                provider        = VALUES(provider),
                message_id      = VALUES(message_id),
                reason          = VALUES(reason),
                processed_at    = VALUES(processed_at),
                sent_at         = VALUES(sent_at)'
        );

        $statement->execute([
            $outcome['campaign_id'],
            $outcome['customer_id'],
            $outcome['request_id'],
            $outcome['promo_code'],
            $outcome['recipient_email'] ?? null,
            $outcome['subject'],
            $outcome['status'],
            $outcome['provider'] ?? null,
            $outcome['message_id'] ?? null,
            $outcome['reason'] ?? null,
            $outcome['processed_at'],
            $outcome['sent_at'] ?? null,
        ]);
    }

    public function saveSent(array $job, string $recipientEmail, string $messageId): void
    {
        $now = gmdate('Y-m-d H:i:s');

        $this->save($this->fromJob($job) + [
            'recipient_email' => $recipientEmail,
            'status'          => 'SENT',
            'provider'        => 'mailtrap',
            'message_id'      => $messageId,
            'reason'          => null,
            'processed_at'    => $now,
            'sent_at'         => $now,
        ]);
    }

    public function saveSkipped(array $job, string $reason): void
    {
        $this->save($this->fromJob($job) + [
            'recipient_email' => null,
            'status'          => 'SKIPPED',
            'provider'        => null,
            'message_id'      => null,
            'reason'          => $reason,
            'processed_at'    => gmdate('Y-m-d H:i:s'),
            'sent_at'         => null,
        ]);
    }

    public function saveFailed(array $job, string $reason): void
    {
        $this->save($this->fromJob($job) + [
            'recipient_email' => null,
            'status'          => 'FAILED',
            'provider'        => 'mailtrap',
            'message_id'      => null,
            'reason'          => $reason,
            'processed_at'    => gmdate('Y-m-d H:i:s'),
            'sent_at'         => null,
        ]);
    }

    /** Maps the camelCase queue job onto the snake_case key columns. */
    private function fromJob(array $job): array
    {
        return [
            'campaign_id' => $job['campaignId'],
            'customer_id' => $job['customerId'],
            'request_id'  => $job['requestId'],
            'promo_code'  => $job['promoCode'],
            'subject'     => $job['subject'],
        ];
    }
}
