<?php

declare(strict_types=1);

namespace Tests\I18n;

use App\I18n\Translator;
use PHPUnit\Framework\TestCase;

class TranslatorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/i18n_test_' . uniqid();
        mkdir($this->dir);
        file_put_contents($this->dir . '/en_US.json', json_encode([
            'nav' => ['dashboard' => 'Dashboard', 'domains' => 'Domains'],
            'user' => ['quota_exceeded' => 'Max is :max MB'],
            'only_en' => 'English only',
        ]));
        file_put_contents($this->dir . '/tr_TR.json', json_encode([
            'nav' => ['dashboard' => 'Panel'],
        ]));
        Translator::setLocaleDir($this->dir);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/en_US.json');
        @unlink($this->dir . '/tr_TR.json');
        @rmdir($this->dir);
        Translator::setLocaleDir(null);
    }

    public function testResolvesNestedKeyInActiveLocale(): void
    {
        Translator::init('tr_TR');
        $this->assertSame('Panel', Translator::translate('nav.dashboard'));
    }

    public function testFallsBackToBaseLocaleWhenKeyMissing(): void
    {
        Translator::init('tr_TR');
        // 'nav.domains' exists only in en_US.
        $this->assertSame('Domains', Translator::translate('nav.domains'));
    }

    public function testReturnsKeyWhenMissingEverywhere(): void
    {
        Translator::init('tr_TR');
        $this->assertSame('nav.nonexistent', Translator::translate('nav.nonexistent'));
    }

    public function testSubstitutesPlaceholders(): void
    {
        Translator::init('en_US');
        $this->assertSame('Max is 100 MB', Translator::translate('user.quota_exceeded', ['max' => 100]));
    }

    public function testUnknownLocaleFallsBackToBase(): void
    {
        Translator::init('xx_XX');
        $this->assertSame('en_US', Translator::currentLocale());
        $this->assertSame('English only', Translator::translate('only_en'));
    }

    public function testMissingLocaleFileYieldsEmptyMessagesNoCrash(): void
    {
        Translator::setLocaleDir($this->dir . '/does-not-exist');
        Translator::init('en_US');
        // No file loaded -> key echoes back, panel does not crash.
        $this->assertSame('nav.dashboard', Translator::translate('nav.dashboard'));
    }

    public function testIsSupported(): void
    {
        $this->assertTrue(Translator::isSupported('en_US'));
        $this->assertTrue(Translator::isSupported('tr_TR'));
        $this->assertFalse(Translator::isSupported('../../etc/passwd'));
        $this->assertFalse(Translator::isSupported('de_DE'));
    }

    public function testAvailableLocales(): void
    {
        $locales = Translator::availableLocales();
        $this->assertArrayHasKey('en_US', $locales);
        $this->assertArrayHasKey('tr_TR', $locales);
        $this->assertSame('Türkçe', $locales['tr_TR']);
    }
}
