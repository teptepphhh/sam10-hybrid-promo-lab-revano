<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

use Lab\ActivityLogger;
use Lab\CampaignPreparer;
use Lab\Database;

$campaignId = $argv[1] ?? '';
$promoCode = $argv[2] ?? '';

// Only letters, numbers, - and _ are allowed, max 64 characters because that is the column size
$pattern = '/^[A-Za-z0-9_-]{1,64}$/';

if (!preg_match($pattern, $campaignId) || !preg_match($pattern, $promoCode)) {
    fwrite(STDERR, 'Usage: php prepare-campaign.php <CAMPAIGN_ID> <PROMO_CODE>' . PHP_EOL);
    fwrite(STDERR, 'Example: php prepare-campaign.php CAMP-001 PROMO15' . PHP_EOL);
    exit(1);
}

try {
    $preparer = new CampaignPreparer((new Database())->connect(), new ActivityLogger());
    $preparer->prepare($campaignId, $promoCode);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
