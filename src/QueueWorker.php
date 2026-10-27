<?php

declare(strict_types=1);

namespace Lab;

use Aws\Sqs\SqsClient;
use RuntimeException;
use Throwable;

// Waits for SQS messages and gives each one to the processor
// The message is deleted only after the result is saved in the database
// If something fails it stays on the queue, comes back later, and ends up in the DLQ
final class QueueWorker
{
    public function __construct(
        private SqsClient $sqs,
        private string $queueUrl,
        private MarketingProcessor $processor,
        private ActivityLogger $logger,
    ) {}

    // 0 means run forever (Ctrl+C to stop). Otherwise stop after N empty polls in a row
    public function run(int $maxIdlePolls = 0): void
    {
        $idlePolls = 0;
        echo "Worker started; polling {$this->queueUrl}" . PHP_EOL;

        while (true) {
            $result = $this->sqs->receiveMessage([
                'QueueUrl'                    => $this->queueUrl,
                'MaxNumberOfMessages'         => 1,
                'WaitTimeSeconds'             => 20,
                'MessageSystemAttributeNames' => ['ApproximateReceiveCount'],
            ]);
            $messages = $result['Messages'] ?? [];

            if ($messages === []) {
                $idlePolls++;
                echo "idle poll {$idlePolls}" . ($maxIdlePolls > 0 ? "/{$maxIdlePolls}" : '') . PHP_EOL;
                if ($maxIdlePolls > 0 && $idlePolls >= $maxIdlePolls) {
                    echo "No messages after {$idlePolls} empty polls; exiting." . PHP_EOL;
                    return;
                }
                continue;
            }

            $idlePolls = 0;
            foreach ($messages as $message) {
                $this->event('received', [
                    'messageId'    => $message['MessageId'],
                    'receiveCount' => (int) ($message['Attributes']['ApproximateReceiveCount'] ?? 0),
                ]);
                $this->handle($message);
            }
        }
    }

    public function handle(array $message): void
    {
        $job = null;
        $phase = 'decode';

        try {
            $job = json_decode((string) $message['Body'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($job)) {
                throw new RuntimeException('Message body is not a JSON object');
            }

            $phase = 'process';
            // TODO W1: give the job to the processor
            $outcome = $this->processor->process($job);

            // TODO M3: remember we are in the delete step, so a delete error is logged as ack_failed
            $phase = 'delete';
            // TODO W2: delete with the ReceiptHandle, not the MessageId. SQS needs the handle from this receive
            $this->sqs->deleteMessage([
                'QueueUrl'      => $this->queueUrl,
                'ReceiptHandle' => $message['ReceiptHandle'],
            ]);

            // TODO M3: log 'deleted' only after the delete worked
            $this->event('deleted', $this->ids($job) + ['messageId' => $message['MessageId'], 'outcome' => $outcome]);
        } catch (Throwable $e) {
            // We do not delete the message. SQS shows it again later, and after too many tries it goes to the DLQ
            $this->event($phase === 'delete' ? 'ack_failed' : 'processing_failed', $this->ids($job) + [
                'messageId' => $message['MessageId'] ?? null,
                'phase'     => $phase,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    private function event(string $event, array $context): void
    {
        $this->logger->record($event, $context);

        $parts = [];
        foreach ($context as $key => $value) {
            $parts[] = "{$key}=" . (is_scalar($value) ? (string) $value : json_encode($value));
        }
        echo str_pad($event, 18) . implode(' ', $parts) . PHP_EOL;
    }

    // Get the IDs from the job, if it was decoded
    private function ids(?array $job): array
    {
        $ids = [];
        foreach (['requestId', 'campaignId', 'customerId'] as $key) {
            if (isset($job[$key]) && is_string($job[$key])) {
                $ids[$key] = $job[$key];
            }
        }

        return $ids;
    }
}
