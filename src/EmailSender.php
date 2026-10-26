<?php

declare(strict_types=1);

namespace Lab;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends one plain-text promotional email through Mailtrap SMTP.
 * SMTP exceptions are deliberately not caught here so the caller can record FAILED.
 */
final class EmailSender
{
    /**
     * @param array{host: string, port: int, username: string, password: string, from_address: string, delay_seconds: int} $settings
     */
    public function __construct(private array $settings) {}

    /**
     * @return string The MIME Message-ID of the sent email.
     */
    public function send(array $customer, array $job): string
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $this->settings['host'];
        $mail->Port = $this->settings['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $this->settings['username'];
        $mail->Password = $this->settings['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // certificate verification stays on
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom($this->settings['from_address'], 'Marketing Lab');
        $mail->isHTML(false);

        // TODO E1 (completed): address the email to the customer from the database.
        $mail->addAddress($customer['email'], $customer['full_name']);

        // TODO E2 (completed): subject from the job and a personalized plain-text body showing the promo code.
        $mail->Subject = $job['subject'];
        $mail->Body = "Hi {$customer['full_name']},\n\n"
            . "{$job['body']}\n\n"
            . "Your promo code: {$job['promoCode']}\n\n"
            . "This is a laboratory test message.";

        $mail->send();

        return $mail->getLastMessageID();
    }
}
