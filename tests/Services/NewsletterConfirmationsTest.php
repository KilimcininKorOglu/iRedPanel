<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\NewsletterConfirmations;
use PHPUnit\Framework\TestCase;

class NewsletterConfirmationsTest extends TestCase
{
    private const NOW = 1_800_000_000;

    public function testRequestWithinResendIntervalIsRecent(): void
    {
        // A repeated form post must not send a second mail for the same pending request.
        $expired = self::NOW - 60 + 24 * 3600;

        $this->assertTrue(NewsletterConfirmations::isRecent($expired, 24, self::NOW));
    }

    public function testRequestOlderThanResendIntervalGetsNewToken(): void
    {
        $expired = self::NOW - NewsletterConfirmations::RESEND_INTERVAL_SECONDS + 24 * 3600;

        $this->assertFalse(NewsletterConfirmations::isRecent($expired, 24, self::NOW));
    }

    public function testExpiredRequestIsNotRecent(): void
    {
        $this->assertFalse(NewsletterConfirmations::isRecent(self::NOW, 24, self::NOW));
    }
}
