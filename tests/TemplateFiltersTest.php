<?php

declare(strict_types=1);

namespace Tests;

use App\TemplateFilters;
use PHPUnit\Framework\TestCase;

class TemplateFiltersTest extends TestCase
{
    public function testLocalizeMapsStatusStrings(): void
    {
        $this->assertSame('✅', TemplateFilters::localize('active'));
        $this->assertSame('❌', TemplateFilters::localize('disabled'));
    }

    /**
     * Model flags such as Admin::$isGlobalAdmin reach the filter as booleans;
     * they must render as the same indicators as their string forms.
     */
    public function testLocalizeMapsBooleans(): void
    {
        $this->assertSame('✅', TemplateFilters::localize(true));
        $this->assertSame('❌', TemplateFilters::localize(false));
    }

    public function testLocalizeEscapesUnknownValues(): void
    {
        $this->assertSame('&lt;b&gt;', TemplateFilters::localize('<b>'));
    }
}
