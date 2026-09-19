<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Exceptions\InvalidInputException;
use App\Models\KeepMailboxDays;
use PHPUnit\Framework\TestCase;

class KeepMailboxDaysTest extends TestCase
{
    public function testDeleteDateCountsFromToday(): void
    {
        $today = new \DateTimeImmutable('2026-12-25');

        $this->assertSame('2027-01-01', KeepMailboxDays::deleteDate(7, $today));
        $this->assertSame('2026-12-26', KeepMailboxDays::deleteDate(1, $today));
    }

    /**
     * cli/deleteExpiredMailboxes.php skips a row without delete_date.
     */
    public function testZeroKeepsTheMailboxForever(): void
    {
        $this->assertNull(KeepMailboxDays::deleteDate(0));
    }

    /**
     * Only a global admin keeps a mailbox forever or longer than a year.
     */
    public function testDomainAdminChoosesFromItsDays(): void
    {
        $this->assertSame(365, KeepMailboxDays::parse('365', false));
        $this->assertSame(0, KeepMailboxDays::parse('0', true));
        $this->assertSame(1095, KeepMailboxDays::parse(1095, true));

        foreach (['0', '', '730'] as $value) {
            try {
                KeepMailboxDays::parse($value, false);
                $this->fail("{$value} must be refused for a domain admin");
            } catch (InvalidInputException $e) {
                $this->assertSame('common.msg_invalid_keep_days', $e->translationKey);
            }
        }
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidValues(): array
    {
        return ['not listed' => ['5'], 'negative' => ['-1'], 'text' => ['abc'], 'array' => [['7']]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidValues')]
    public function testValueOutsideTheListIsRefused(mixed $value): void
    {
        $this->expectException(InvalidInputException::class);
        KeepMailboxDays::parse($value, true);
    }
}
