<?php

declare(strict_types=1);

namespace Tests;

use App\BadgeTone;
use PHPUnit\Framework\TestCase;

class BadgeToneTest extends TestCase
{
    public function testDangerousValuesAreRedOnEveryPage(): void
    {
        $this->assertSame('red', BadgeTone::tone('log_event', 'delete'));
        $this->assertSame('red', BadgeTone::tone('mail_content', 'V'));
        $this->assertSame('red', BadgeTone::tone('wblist', 'B'));
    }

    public function testAllowedValuesAreGreen(): void
    {
        $this->assertSame('green', BadgeTone::tone('access_policy', 'public'));
        $this->assertSame('green', BadgeTone::tone('mail_content', 'C'));
        $this->assertSame('green', BadgeTone::tone('wblist', 'W'));
        $this->assertSame('green', BadgeTone::tone('ownership', 'verified'));
    }

    public function testTheExpiryStateCarriesItsOwnColors(): void
    {
        $this->assertSame('red', BadgeTone::tone('expiry', 'expired'));
        $this->assertSame('orange', BadgeTone::tone('expiry', 'soon'));
        $this->assertSame('gray', BadgeTone::tone('expiry', 'valid'));
    }

    public function testLookupIgnoresCase(): void
    {
        // Aliases store "membersOnly", mailing lists "membersonly".
        $this->assertSame('cyan', BadgeTone::tone('access_policy', 'membersOnly'));
        $this->assertSame('cyan', BadgeTone::tone('access_policy', 'membersonly'));
    }

    public function testUnknownValueOrCategoryIsGray(): void
    {
        $this->assertSame(BadgeTone::FALLBACK, BadgeTone::tone('log_event', 'grant'));
        $this->assertSame(BadgeTone::FALLBACK, BadgeTone::tone('no_such_category', 'delete'));
    }

    public function testClassesCombineBaseAndToneClass(): void
    {
        $this->assertSame('tone-badge tone-purple', BadgeTone::classes('admin_type', 'standalone'));
    }
}
