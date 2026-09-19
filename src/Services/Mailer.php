<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MailDeliveryException;
use App\Models\Settings;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends mail through the configured SMTP server. The panel image has no
 * sendmail binary, so PHP mail() cannot deliver.
 */
class Mailer
{
    private const TIMEOUT_SECONDS = 15;

    private const SECURITY_MODES = [
        'none' => '',
        'starttls' => PHPMailer::ENCRYPTION_STARTTLS,
        'tls' => PHPMailer::ENCRYPTION_SMTPS,
    ];

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $security,
        private readonly bool $tlsVerify,
        private readonly string $user,
        private readonly string $password,
        private readonly string $from,
        private readonly string $fromName,
    ) {}

    /**
     * @throws MailDeliveryException when SMTP is not configured
     */
    public static function fromSettings(): self
    {
        $settings = Settings::getInstance();
        if ($settings->smtpHost === '' || $settings->smtpFrom === '') {
            throw new MailDeliveryException('SMTP is not configured: set IREDPANEL_SMTP_HOST and IREDPANEL_SMTP_FROM');
        }

        return new self(
            $settings->smtpHost,
            $settings->smtpPort,
            $settings->smtpSecurity,
            $settings->smtpTlsVerify,
            $settings->smtpUser,
            $settings->smtpPassword,
            $settings->smtpFrom,
            $settings->brandName,
        );
    }

    /**
     * @throws MailDeliveryException when the SMTP server does not accept the message
     */
    public function send(string $to, string $subject, string $body, bool $isHtml = false): void
    {
        $mail = $this->configure(new PHPMailer(true));

        try {
            $mail->setFrom($this->from, $this->fromName);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML($isHtml);
            $mail->Body = $body;
            $mail->send();
        } catch (PHPMailerException $e) {
            throw new MailDeliveryException('SMTP delivery failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Applies the transport settings to a PHPMailer instance.
     */
    public function configure(PHPMailer $mail): PHPMailer
    {
        $mail->isSMTP();
        $mail->Host = $this->host;
        $mail->Port = $this->port;
        $mail->Timeout = self::TIMEOUT_SECONDS;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->SMTPSecure = self::SECURITY_MODES[$this->security]
            ?? throw new \InvalidArgumentException("Unsupported SMTP security: {$this->security}");
        // With "none" PHPMailer would still upgrade to STARTTLS when offered.
        $mail->SMTPAutoTLS = $this->security !== 'none';
        if (!$this->tlsVerify) {
            $mail->SMTPOptions = ['ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]];
        }
        $mail->SMTPAuth = $this->user !== '';
        $mail->Username = $this->user;
        $mail->Password = $this->password;

        return $mail;
    }
}
