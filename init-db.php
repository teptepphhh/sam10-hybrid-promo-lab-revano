<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

use Lab\Database;
use Lab\LocalDatabaseTools;

$tools = new LocalDatabaseTools((new Database())->connect());
$tools->initialize(__DIR__ . '/schema.sql');
