<?php

declare(strict_types=1);

namespace Tests;

use App\HelpText;
use App\I18n\Translator;
use PHPUnit\Framework\TestCase;

class HelpTextTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/help_test_' . uniqid();
        mkdir($this->dir);
        file_put_contents($this->dir . '/en_US.json', json_encode([
            'common' => ['help' => 'Help'],
            'spampolicy' => [
                'tag_level' => 'Tag level',
                'tag_level_help' => 'A "low" value marks more mail.',
                'kill_level' => 'Block level',
            ],
        ]));
        Translator::setLocaleDir($this->dir);
        Translator::init('en_US');
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/en_US.json');
        @rmdir($this->dir);
        Translator::setLocaleDir(null);
    }

    public function testALabelWithoutAnExplanationGetsNoIcon(): void
    {
        $this->assertSame('', HelpText::icon('spampolicy.kill_level'));
    }

    public function testTheIconCarriesTheLabelAndTheExplanation(): void
    {
        $icon = HelpText::icon('spampolicy.tag_level');

        $this->assertStringContainsString('data-bs-toggle="popover"', $icon);
        $this->assertStringContainsString('data-bs-title="Tag level"', $icon);
        $this->assertStringContainsString('aria-label="Help"', $icon);
        $this->assertStringContainsString('bi-question-circle', $icon);
    }

    public function testTheExplanationIsEscaped(): void
    {
        $icon = HelpText::icon('spampolicy.tag_level');

        $this->assertStringContainsString('&quot;low&quot;', $icon);
        $this->assertStringNotContainsString('a "low" value', $icon);
    }
}
