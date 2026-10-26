<?php

declare(strict_types=1);

// Shared bootstrap: every root script starts with require __DIR__ . '/config.php';
// All secrets (DB_USER, DB_PASSWORD, AWS_PROFILE, MAILTRAP_*) come from environment variables.

require __DIR__ . '/vendor/autoload.php';
