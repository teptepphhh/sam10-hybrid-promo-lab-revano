<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

use Lab\ActivityLogger;
use Lab\Database;
use Lab\QueuePublisher;
use Lab\Settings;

$campaignId = $argv[1] ?? '';
$customerId = $argv[2] ?? '';

if ($campaignId === '' || $customerId === '') {
    fwrite(STDERR, 'Usage: php resubmit.php <CAMPAIGN_ID> <CUSTOMER_ID>' . PHP_EOL);
    fwrite(STDERR, 'Example: php resubmit.php CAMP-001 CUST-00042' . PHP_EOL);
    exit(1);
}

try {
    $publisher = new QueuePublisher(
        (new Database())->connect(),
        Settings::queue(),
        Settings::required('SQS_QUEUE_URL'),
        new ActivityLogger(),
    );
    $publisher->resubmit($campaignId, $customerId);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
