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
}
