<?php

declare(strict_types=1);

namespace Tests\I18n;

use App\I18n\LocaleResolver;
use PHPUnit\Framework\TestCase;

class LocaleResolverTest extends TestCase
{
    protected function setUp(): void
    {
        unset($_SESSION['lang'], $_COOKIE[LocaleResolver::COOKIE_NAME]);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['lang'], $_COOKIE[LocaleResolver::COOKIE_NAME]);
    }

    public function testSessionTakesPriority(): void
    {
        $_SESSION['lang'] = 'tr_TR';
        $_COOKIE[LocaleResolver::COOKIE_NAME] = 'en_US';
        $this->assertSame('tr_TR', LocaleResolver::resolve());
    }

    public function testCookieUsedWhenSessionAbsent(): void
    {
        $_COOKIE[LocaleResolver::COOKIE_NAME] = 'tr_TR';
        $this->assertSame('tr_TR', LocaleResolver::resolve());
    }

    public function testFallsBackToDefaultWhenNothingSet(): void
    {
        // Settings default in the test env is en_US (no override).
        $this->assertSame('en_US', LocaleResolver::resolve());
    }

    public function testInvalidSessionIgnoredFallsThroughToCookie(): void
    {
        $_SESSION['lang'] = '../../etc/passwd';
        $_COOKIE[LocaleResolver::COOKIE_NAME] = 'tr_TR';
        $this->assertSame('tr_TR', LocaleResolver::resolve());
    }

    public function testInvalidCookieIgnored(): void
    {
        $_COOKIE[LocaleResolver::COOKIE_NAME] = 'xx_XX';
        $this->assertSame('en_US', LocaleResolver::resolve());
    }

    public function testPersistCookieRejectsUnsupportedLocaleWithoutError(): void
    {
        // Unsupported locale returns before setcookie(); no headers emitted.
        LocaleResolver::persistCookie('xx_XX');
        $this->expectNotToPerformAssertions();
    }
}
