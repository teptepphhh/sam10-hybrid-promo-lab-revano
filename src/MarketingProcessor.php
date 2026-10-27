<?php

declare(strict_types=1);

namespace Lab;

use InvalidArgumentException;
use PDO;
use Throwable;

// Handles one job from the queue: check for duplicates, check consent, send, save the result
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

    // Returns 'SENT', 'SKIPPED' or 'DUPLICATE'
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

        // TODO M2: log that we started this job
        $this->logger->record('processing_started', $ids);

        // TODO P1: if there is already a SENT or SKIPPED row, stop here. Never send twice
        if ($this->history->isComplete($job['campaignId'], $job['customerId'])) {
            $this->logger->record('duplicate_skipped', $ids);
            return 'DUPLICATE';
        }

        // TODO P2: find the customer with a prepared statement
        $statement = $this->db->prepare(
            'SELECT customer_id, full_name, email, marketing_opt_in FROM customers WHERE customer_id = ?'
        );
        $statement->execute([$job['customerId']]);
        $customer = $statement->fetch();

        // TODO P3: skip if the customer does not exist or did not opt in
        $reason = null;
        if ($customer === false) {
            $reason = 'Customer not found';
        } elseif ((int) $customer['marketing_opt_in'] !== 1) {
            $reason = 'Customer has not opted in to marketing';
        }
        if ($reason !== null) {
            $this->history->saveSkipped($job, $reason);
            // TODO M2: log the skip and the reason
            $this->logger->record('skipped', $ids + ['reason' => $reason]);
            return 'SKIPPED';
        }

        // TODO P4: send with EmailSender and keep the Message-ID
        // Only the send is inside try. No database transaction is open while we wait for SMTP
        try {
            $messageId = $this->sender->send($customer, $job);
        } catch (Throwable $e) {
            $this->history->saveFailed($job, $e->getMessage());
            throw $e;
        }

        // TODO P5: save the SENT row with the email, provider, Message-ID and sent_at
        $this->history->saveSent($job, $customer['email'], $messageId);

        // TODO M2: log 'sent' only after the SENT row is saved
        $this->logger->record('sent', $ids + ['messageId' => $messageId]);

        // Mailtrap free plan only allows about 1 email every 10 seconds, so we wait 11 to be safe
        sleep(max(0, $this->sendDelaySeconds));

        return 'SENT';
    }
}
