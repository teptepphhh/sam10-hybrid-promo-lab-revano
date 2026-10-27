<?php

declare(strict_types=1);

namespace Lab;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

// Writes one JSON line per event to the log file. No personal data or secrets go in
final class ActivityLogger
{
    // These keys are never written to the log (names, emails, passwords, email text)
    // We lowercase the key and drop "_" first, so full_name and fullName both match
    private const FORBIDDEN_KEYS = [
        'name', 'fullname', 'firstname', 'lastname',
        'email', 'recipient', 'recipientemail', 'to', 'from', 'fromaddress',
        'password', 'username', 'token', 'secret', 'apikey', 'accesskey', 'secretkey', 'credentials',
        'body', 'messagebody', 'html', 'text', 'subject',
    ];

    private string $logPath;

    // Default log file is logs/events.jsonl. Make the folder if it is not there yet
    public function __construct(?string $logPath = null)
    {
        $this->logPath = $logPath ?? dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'events.jsonl';

        $directory = dirname($this->logPath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create log directory: {$directory}");
        }
    }

    // LOCK_EX so two scripts writing at the same time do not mix up their lines
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
                // Error messages can have email addresses in them (like PHPMailer errors), so hide them
                $value = preg_replace('/[^\s<>@"\':;,]+@[^\s<>@"\':;,]+/', '[redacted-email]', $value);
            }
            $clean[$key] = $value;
        }

        return $clean;
    }
}
