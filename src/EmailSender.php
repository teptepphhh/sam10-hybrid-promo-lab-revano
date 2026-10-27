<?php

declare(strict_types=1);

namespace Lab;

use PHPMailer\PHPMailer\PHPMailer;

// Sends one plain text promo email through Mailtrap
// We do not catch errors here so the caller can save FAILED
final class EmailSender
{
    public function __construct(private array $settings) {}

    // Returns the Message-ID of the sent email
    public function send(array $customer, array $job): string
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $this->settings['host'];
        $mail->Port = $this->settings['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $this->settings['username'];
        $mail->Password = $this->settings['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // keep checking the certificate
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom($this->settings['from_address'], 'Marketing Lab');
        $mail->isHTML(false);

        // TODO E1: send to the customer's email from the database
        $mail->addAddress($customer['email'], $customer['full_name']);

        // TODO E2: use the job subject and a short personal message with the promo code
        $mail->Subject = $job['subject'];
        $mail->Body = "Hi {$customer['full_name']},\n\n"
            . "{$job['body']}\n\n"
            . "Your promo code: {$job['promoCode']}\n\n"
            . "This is a laboratory test message.";

        $mail->send();

        return $mail->getLastMessageID();
    }
}
