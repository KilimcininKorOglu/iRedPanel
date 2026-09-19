<?php

declare(strict_types=1);

namespace Tests;

use App\I18n\Translator;
use App\TemplateFilters;
use PHPUnit\Framework\TestCase;

class TemplateFiltersTest extends TestCase
{
    protected function setUp(): void
    {
        Translator::init('en_US');
    }

    public function testLocalizeMapsStatusStrings(): void
    {
        $this->assertStringContainsString('bi-check-circle-fill', TemplateFilters::localize('active'));
        $this->assertStringContainsString('bi-x-circle', TemplateFilters::localize('disabled'));
    }

    /**
     * Model flags such as Admin::$isGlobalAdmin reach the filter as booleans;
     * they must render as the same indicators as their string forms.
     */
    public function testLocalizeMapsBooleans(): void
    {
        $this->assertSame(TemplateFilters::localize('yes'), TemplateFilters::localize(true));
        $this->assertSame(TemplateFilters::localize('no'), TemplateFilters::localize(false));
    }

    public function testIndicatorCarriesTheTranslatedLabel(): void
    {
        // The icon has no text, so screen readers need the label.
        $this->assertStringContainsString('aria-label="Yes"', TemplateFilters::localize(true));
        $this->assertStringContainsString('aria-label="No"', TemplateFilters::localize(false));
    }

    public function testLocalizeEscapesUnknownValues(): void
    {
        $this->assertSame('&lt;b&gt;', TemplateFilters::localize('<b>'));
    }
}
