<?php

declare(strict_types=1);

namespace Lab;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * Appends structured JSON Lines events to a log file. Never logs personal data or secrets.
 */
final class ActivityLogger
{
    /**
     * Context keys that must never reach the log (names, emails, credentials, message bodies).
     * Keys are compared after lowercasing and removing "_", so full_name, fullName and FULLNAME all match.
     * Allowed camelCase identifiers (requestId, campaignId, customerId, messageId, phase, reason, error) pass through.
     */
    private const FORBIDDEN_KEYS = [
        'name', 'fullname', 'firstname', 'lastname',
        'email', 'recipient', 'recipientemail', 'to', 'from', 'fromaddress',
        'password', 'username', 'token', 'secret', 'apikey', 'accesskey', 'secretkey', 'credentials',
        'body', 'messagebody', 'html', 'text', 'subject',
    ];

    private string $logPath;

    // Accepts an optional log path (default logs/events.jsonl) and creates the directory.
    public function __construct(?string $logPath = null)
    {
        $this->logPath = $logPath ?? dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'events.jsonl';

        $directory = dirname($this->logPath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create log directory: {$directory}");
        }
    }

    // Appends one UTC JSON line with FILE_APPEND | LOCK_EX after stripping sensitive keys.
    public function record(string $event, array $context = []): void
    {
        $timestamp = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');

        $entry = ['timestamp' => $timestamp, 'event' => $event] + $this->sanitize($context);

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        file_put_contents($this->logPath, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function sanitize(array $context): array
    {
        $clean = [];

        foreach ($context as $key => $value) {
            if (in_array(str_replace('_', '', strtolower((string) $key)), self::FORBIDDEN_KEYS, true)) {
                continue;
            }
            if ($key === 'timestamp' || $key === 'event') {
                continue;
            }
            if (is_array($value)) {
                $value = $this->sanitize($value);
            } elseif (is_string($value)) {
                // Error messages (e.g. PHPMailer "recipients failed: x@y") can embed addresses; mask them.
                $value = preg_replace('/[^\s<>@"\':;,]+@[^\s<>@"\':;,]+/', '[redacted-email]', $value);
            }
            $clean[$key] = $value;
        }

        return $clean;
    }
}
