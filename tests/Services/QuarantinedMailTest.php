<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\QuarantinedMail;
use PHPUnit\Framework\TestCase;

class QuarantinedMailTest extends TestCase
{
    private const MESSAGE = "Date: Mon, 1 Sep 2026 10:00:00 +0300\r\n"
        . "From: sender@example.com\r\n"
        . "Subject: a long\r\n subject line\r\n"
        . "X-Spam-Status: Yes, score=9.2 required=6.2\r\n"
        . "Subject: forged\r\n"
        . "\r\n"
        . "Body line one\r\nBody line two\r\n";

    public function testHeadersAreParsed(): void
    {
        $headers = QuarantinedMail::headers(self::MESSAGE);

        $this->assertSame('sender@example.com', $headers['from']);
        $this->assertSame('Yes, score=9.2 required=6.2', $headers['x-spam-status']);
    }

    public function testFoldedHeaderIsJoined(): void
    {
        $this->assertSame('a long subject line', QuarantinedMail::headers(self::MESSAGE)['subject']);
    }

    public function testRepeatedHeaderKeepsTheFirstValue(): void
    {
        $this->assertNotSame('forged', QuarantinedMail::headers(self::MESSAGE)['subject']);
    }

    public function testBodyStartsAfterTheEmptyLine(): void
    {
        $this->assertSame("Body line one\r\nBody line two\r\n", QuarantinedMail::body(self::MESSAGE));
    }

    public function testBodyIsTruncatedToTheLimit(): void
    {
        $this->assertSame('Body', QuarantinedMail::body(self::MESSAGE, 4));
    }

    public function testHeaderTextHoldsNoBody(): void
    {
        $this->assertStringNotContainsString('Body line one', QuarantinedMail::headerText(self::MESSAGE));
        $this->assertStringContainsString('X-Spam-Status', QuarantinedMail::headerText(self::MESSAGE));
    }

    public function testMessageWithoutAnEmptyLineHasNoBody(): void
    {
        $this->assertSame('', QuarantinedMail::body("Subject: only headers\r\n"));
        $this->assertSame('Subject: only headers', QuarantinedMail::headerText("Subject: only headers\r\n"));
    }

    public function testLineFeedOnlyMessageIsParsed(): void
    {
        $raw = "From: a@example.com\nSubject: plain\n\nbody\n";

        $this->assertSame('plain', QuarantinedMail::headers($raw)['subject']);
        $this->assertSame("body\n", QuarantinedMail::body($raw));
    }

    public function testFileNameKeepsOnlySafeCharacters(): void
    {
        $this->assertSame('abc123.eml', QuarantinedMail::fileName('abc123'));
        $this->assertSame('_.._etc_passwd.eml', QuarantinedMail::fileName('/../etc/passwd'));
        $this->assertSame('message.eml', QuarantinedMail::fileName(''));
    }
}
