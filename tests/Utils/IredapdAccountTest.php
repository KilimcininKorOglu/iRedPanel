<?php

declare(strict_types=1);

namespace Tests\Utils;

use App\Utils\IredapdAccount;
use PHPUnit\Framework\TestCase;

class IredapdAccountTest extends TestCase
{
    public function testAnAccountOutranksTheGlobalSetting(): void
    {
        // iRedAPD uses the first row after ORDER BY priority DESC, so a
        // mailbox or domain row must rank above the global '@.' row.
        $this->assertSame(100, IredapdAccount::priority('user@domain.com'));
        $this->assertSame(60, IredapdAccount::priority('@domain.com'));
        $this->assertSame(50, IredapdAccount::priority('@.domain.com'));
        $this->assertSame(0, IredapdAccount::priority('@.'));
    }

    public function testIsValidMatchesThePriorityRules(): void
    {
        $this->assertTrue(IredapdAccount::isValid('@.'));
        $this->assertTrue(IredapdAccount::isValid('192.0.2.0/24'));
        $this->assertFalse(IredapdAccount::isValid('garbage-account'));
        $this->assertFalse(IredapdAccount::isValid(''));
    }

    public function testRejectsAnAccountThatIredapdCannotMatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        IredapdAccount::priority('garbage-account');
    }
}
