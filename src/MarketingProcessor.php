<?php

declare(strict_types=1);

namespace Lab;

use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Processes one queue job: deduplicates, checks consent, sends, and records the outcome.
 */
final class MarketingProcessor
{
    private const REQUIRED_FIELDS = ['requestId', 'campaignId', 'customerId', 'promoCode', 'subject', 'body'];

    public function __construct(
        private PDO $db,
        private EmailHistory $history,
        private EmailSender $sender,
        private ActivityLogger $logger,
        private int $sendDelaySeconds,
    ) {}

    /**
     * @return string 'SENT', 'SKIPPED' or 'DUPLICATE'
     */
    public function process(array $job): string
    {
        $missing = [];
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($job[$field]) || !is_string($job[$field]) || trim($job[$field]) === '') {
                $missing[] = $field;
            }
        }
        if ($missing !== []) {
            throw new InvalidArgumentException('Invalid job; missing or empty fields: ' . implode(', ', $missing));
        }

        $ids = [
            'requestId'  => $job['requestId'],
            'campaignId' => $job['campaignId'],
            'customerId' => $job['customerId'],
        ];

        // TODO M2 (completed): log that processing of this job has started.
        $this->logger->record('processing_started', $ids);

        // TODO P1 (completed): a SENT/SKIPPED history row means this pair is done; never send twice.
        if ($this->history->isComplete($job['campaignId'], $job['customerId'])) {
            $this->logger->record('duplicate_skipped', $ids);
            return 'DUPLICATE';
        }

        // TODO P2 (completed): look up the customer with a prepared statement.
        $statement = $this->db->prepare(
            'SELECT customer_id, full_name, email, marketing_opt_in FROM customers WHERE customer_id = ?'
        );
        $statement->execute([$job['customerId']]);
        $customer = $statement->fetch();

        // TODO P3 (completed): skip unknown customers and customers without marketing consent.
        $reason = null;
        if ($customer === false) {
            $reason = 'Customer not found';
        } elseif ((int) $customer['marketing_opt_in'] !== 1) {
            $reason = 'Customer has not opted in to marketing';
        }
        if ($reason !== null) {
            $this->history->saveSkipped($job, $reason);
            // TODO M2 (completed): log the skip and its reason.
            $this->logger->record('skipped', $ids + ['reason' => $reason]);
            return 'SKIPPED';
        }

        // TODO P4 (completed): send through the injected EmailSender and keep the MIME Message-ID.
        // Only the send is wrapped; no DB transaction is open during SMTP.
        try {
            $messageId = $this->sender->send($customer, $job);
        } catch (Throwable $e) {
            $this->history->saveFailed($job, $e->getMessage());
            throw $e;
        }

        // TODO P5 (completed): record the SENT outcome with recipient, provider, Message-ID and sent_at.
        $this->history->saveSent($job, $customer['email'], $messageId);

        // TODO M2 (completed): log 'sent' only after the SENT row is saved.
        $this->logger->record('sent', $ids + ['messageId' => $messageId]);

        // Mailtrap free plan allows about 1 email per 10 seconds.
        sleep(max(0, $this->sendDelaySeconds));

        return 'SENT';
    }
}
