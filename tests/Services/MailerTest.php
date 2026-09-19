<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\Mailer;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;

class MailerTest extends TestCase
{
    private function mailer(string $security, bool $tlsVerify = true, string $user = 'u@example.com'): Mailer
    {
        return new Mailer('smtp.example.com', 587, $security, $tlsVerify, $user, 'secret', 'from@example.com', 'Panel');
    }

    public function testNoneSecurityDisablesOpportunisticStarttls(): void
    {
        // PHPMailer upgrades to STARTTLS on its own when the server offers it,
        // so "none" must switch that off or a plain-text setup still negotiates TLS.
        $mail = $this->mailer('none')->configure(new PHPMailer(true));

        $this->assertSame('', $mail->SMTPSecure);
        $this->assertFalse($mail->SMTPAutoTLS);
    }

    public function testStarttlsAndImplicitTlsMapToPhpmailerModes(): void
    {
        $this->assertSame(PHPMailer::ENCRYPTION_STARTTLS, $this->mailer('starttls')->configure(new PHPMailer(true))->SMTPSecure);
        $this->assertSame(PHPMailer::ENCRYPTION_SMTPS, $this->mailer('tls')->configure(new PHPMailer(true))->SMTPSecure);
    }

    public function testCertificateChecksStayOnUnlessDisabled(): void
    {
        $verified = $this->mailer('starttls')->configure(new PHPMailer(true));
        $this->assertSame([], $verified->SMTPOptions);

        $unverified = $this->mailer('starttls', false)->configure(new PHPMailer(true));
        $this->assertFalse($unverified->SMTPOptions['ssl']['verify_peer']);
    }

    public function testAuthenticationOnlyWithUser(): void
    {
        $this->assertTrue($this->mailer('starttls')->configure(new PHPMailer(true))->SMTPAuth);
        $this->assertFalse($this->mailer('starttls', true, '')->configure(new PHPMailer(true))->SMTPAuth);
    }

    public function testUnknownSecurityModeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->mailer('ssl')->configure(new PHPMailer(true));
    }
}
