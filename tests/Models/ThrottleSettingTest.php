<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Models\ThrottleSetting;
use PHPUnit\Framework\TestCase;

class ThrottleSettingTest extends TestCase
{
    public function testAnOmittedLimitInheritsInsteadOfBecomingUnlimited(): void
    {
        // iRedAPD reads 0 as unlimited, so a limit the admin never entered must
        // not hide the domain or global limit below it.
        $setting = ThrottleSetting::fromInput(['kind' => 'outbound', 'period' => '3600', 'maxMsgs' => '100']);

        $this->assertSame(100, $setting->maxMsgs);
        $this->assertSame(ThrottleSetting::INHERIT, $setting->maxQuota);
        $this->assertSame(ThrottleSetting::INHERIT, $setting->msgSize);
    }

    public function testAnEmptyFieldInherits(): void
    {
        $setting = ThrottleSetting::fromInput(['period' => '60', 'msgSize' => ' ']);
        $this->assertSame(ThrottleSetting::INHERIT, $setting->msgSize);
    }

    public function testKeepsAnExplicitUnlimitedValue(): void
    {
        $setting = ThrottleSetting::fromInput(['period' => 60, 'msgSize' => 0]);
        $this->assertSame(0, $setting->msgSize);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'zero period is skipped by iRedAPD' => [['period' => '0'], 'period'],
            'limit below -1' => [['period' => '60', 'maxMsgs' => '-2'], 'maxMsgs'],
            'non-numeric limit' => [['period' => '60', 'maxQuota' => '10MB'], 'maxQuota'],
            'float from JSON' => [['period' => 60, 'msgSize' => 1.5], 'msgSize'],
            'unknown kind' => [['kind' => 'sideways', 'period' => '60'], 'kind'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidInputs')]
    public function testRejectsAnInvalidValueAndNamesTheField(array $input, string $field): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($field);
        ThrottleSetting::fromInput($input);
    }
}
