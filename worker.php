<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

use Lab\ActivityLogger;
use Lab\Database;
use Lab\EmailHistory;
use Lab\EmailSender;
use Lab\MarketingProcessor;
use Lab\QueueWorker;
use Lab\Settings;

// Usage: php worker.php [--idle-exit=N]   (default 0 = run until Ctrl+C)
$idleExit = 0;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--idle-exit=(\d+)$/', $arg, $match)) {
        $idleExit = (int) $match[1];
    } else {
        fwrite(STDERR, 'Usage: php worker.php [--idle-exit=N]' . PHP_EOL);
        exit(1);
    }
}

$db = (new Database())->connect();
$mail = Settings::mail();
$logger = new ActivityLogger();

$processor = new MarketingProcessor(
    $db,
    new EmailHistory($db),
    new EmailSender($mail),
    $logger,
    (int) $mail['delay_seconds'],
);

$worker = new QueueWorker(Settings::queue(), Settings::required('SQS_QUEUE_URL'), $processor, $logger);
$worker->run($idleExit);
