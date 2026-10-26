<?php

declare(strict_types=1);

namespace Lab;

use Aws\Sqs\SqsClient;
use RuntimeException;

/**
 * Reads configuration from environment variables. Never hard-codes secrets.
 */
final class Settings
{
    // required() fails fast when an environment variable is missing or empty.
    public static function required(string $name): string
    {
        $value = getenv($name);

        if ($value === false || trim($value) === '') {
            throw new RuntimeException("Missing required environment variable: {$name}");
        }

        return $value;
    }

    // queue() builds an SQS client; credentials are resolved from AWS_PROFILE by the SDK.
    public static function queue(): SqsClient
    {
        return new SqsClient([
            'region'  => self::required('AWS_REGION'),
            'version' => 'latest',
        ]);
    }

    // mail() returns Mailtrap SMTP settings, with MAILTRAP_DELAY_SECONDS defaulting to 11.
    /**
     * @return array{host: string, port: int, username: string, password: string, from_address: string, delay_seconds: int}
     */
    public static function mail(): array
    {
        $delay = getenv('MAILTRAP_DELAY_SECONDS');

        return [
            'host'          => self::required('MAILTRAP_HOST'),
            'port'          => (int) self::required('MAILTRAP_PORT'),
            'username'      => self::required('MAILTRAP_USERNAME'),
            'password'      => self::required('MAILTRAP_PASSWORD'),
            'from_address'  => self::required('MAIL_FROM_ADDRESS'),
            'delay_seconds' => ($delay === false || trim($delay) === '') ? 11 : (int) $delay,
        ];
    }
}
