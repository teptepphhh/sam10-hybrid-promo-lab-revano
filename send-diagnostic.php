<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

use Lab\ActivityLogger;
use Lab\Database;
use Lab\QueuePublisher;
use Lab\Settings;

$campaignId = $argv[1] ?? '';
$customerId = $argv[2] ?? '';
$promoCode = $argv[3] ?? '';

if ($campaignId === '' || $customerId === '' || $promoCode === '') {
    fwrite(STDERR, 'Usage: php send-diagnostic.php <CAMPAIGN_ID> <CUSTOMER_ID> <PROMO_CODE>' . PHP_EOL);
    fwrite(STDERR, 'Example: php send-diagnostic.php CAMP-RECOVERY CUST-08001 PROMO15' . PHP_EOL);
    exit(1);
}

try {
    $publisher = new QueuePublisher(
        (new Database())->connect(),
        Settings::queue(),
        Settings::required('SQS_QUEUE_URL'),
        new ActivityLogger(),
    );
    $publisher->publishDiagnostic($campaignId, $customerId, $promoCode);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
