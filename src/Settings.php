<?php

declare(strict_types=1);

namespace Lab;

use Aws\Sqs\SqsClient;
use RuntimeException;

// Reads settings from environment variables. No secrets in the code
final class Settings
{
    // Stop right away if the variable is missing or empty
    public static function required(string $name): string
    {
        $value = getenv($name);

        if ($value === false || trim($value) === '') {
            throw new RuntimeException("Missing required environment variable: {$name}");
        }

        return $value;
    }

    // The AWS SDK gets the keys from AWS_PROFILE by itself
    public static function queue(): SqsClient
    {
        return new SqsClient([
            'region'  => self::required('AWS_REGION'),
            'version' => 'latest',
        ]);
    }

    // Mailtrap settings. The delay is 11 seconds if MAILTRAP_DELAY_SECONDS is not set
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
