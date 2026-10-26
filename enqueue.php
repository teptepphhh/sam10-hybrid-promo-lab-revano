<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

use Lab\ActivityLogger;
use Lab\Database;
use Lab\QueuePublisher;
use Lab\Settings;

$publisher = new QueuePublisher(
    (new Database())->connect(),
    Settings::queue(),
    Settings::required('SQS_QUEUE_URL'),
    new ActivityLogger(),
);
$publisher->publishPending();
