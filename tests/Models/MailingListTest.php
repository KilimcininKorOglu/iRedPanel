<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Models\MailingList;
use PHPUnit\Framework\TestCase;

class MailingListTest extends TestCase
{
    /**
     * The Postfix mlmmj pipe delivers to /var/vmail/mlmmj/${nexthop},
     * and mlmmjadmin creates the spool at <domain>/<listname>.
     */
    public function testTransportPointsAtTheMlmmjSpoolDirectory(): void
    {
        $this->assertSame('mlmmj:example.com/team', MailingList::transportFor('Team@Example.com'));
    }

    public function testTransportRejectsAddressWithoutDomain(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MailingList::transportFor('team');
    }

    /**
     * maillists.mlid is UNIQUE, so every list needs its own UUID.
     */
    public function testGeneratedIdsAreUniqueUuidV4(): void
    {
        $first = MailingList::generateId();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $first
        );
        $this->assertNotSame($first, MailingList::generateId());
    }

    /**
     * An empty form field means unlimited; form strings and JSON integers are both accepted.
     */
    public function testMaxMsgSizeAcceptsWholeNumbers(): void
    {
        $this->assertSame(0, MailingList::validMaxMsgSize(''));
        $this->assertSame(0, MailingList::validMaxMsgSize(null));
        $this->assertSame(1024, MailingList::validMaxMsgSize(' 1024 '));
        $this->assertSame(2048, MailingList::validMaxMsgSize(2048));
    }

    /**
     * mlmmj treats a negative size as unlimited, so storing it would make the
     * account and the list spool disagree.
     *
     * @return array<string, array{mixed}>
     */
    public static function invalidMaxMsgSizes(): array
    {
        return [
            'negative string' => ['-5'],
            'negative int' => [-5],
            'text' => ['abc'],
            'decimal' => ['1.5'],
            'float' => [1.5],
            'bool' => [true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidMaxMsgSizes')]
    public function testMaxMsgSizeRejectsOtherValues(mixed $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MailingList::validMaxMsgSize($value);
    }
}
