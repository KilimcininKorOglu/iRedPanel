<?php

declare(strict_types=1);

namespace Tests\Utils;

use App\Utils\ExpiryDate;
use PHPUnit\Framework\TestCase;

/**
 * The parts of the expiry date that the cron script and the login paths use.
 */
class ExpiryDateEnforcementTest extends TestCase
{
    public function testTheSqlCutoffIsTheStartOfToday(): void
    {
        $this->assertSame('2026-03-02 00:00:00', ExpiryDate::sqlCutoff((int) strtotime('2026-03-02 15:30:00')));
    }

    public function testAnAccountWithoutADatePassesTheLoginCheck(): void
    {
        $this->expectNotToPerformAssertions();

        ExpiryDate::assertNotExpired('user@test.com', '');
    }

    public function testAnExpiredAccountIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);
        ExpiryDate::assertNotExpired('user@test.com', '2000-01-01');
    }

    public function testTheStateOfADateDrivesTheBadge(): void
    {
        $now = (int) strtotime('2026-03-02 12:00:00');

        $this->assertSame(ExpiryDate::STATE_NONE, ExpiryDate::state('', $now));
        $this->assertSame(ExpiryDate::STATE_EXPIRED, ExpiryDate::state('2026-03-01', $now));
        $this->assertSame(ExpiryDate::STATE_SOON, ExpiryDate::state('2026-03-02', $now));
        $this->assertSame(ExpiryDate::STATE_SOON, ExpiryDate::state('2026-03-31', $now));
        $this->assertSame(ExpiryDate::STATE_VALID, ExpiryDate::state('2026-04-01', $now));
    }
}
