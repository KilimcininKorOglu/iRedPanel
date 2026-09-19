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

    public function testThrottleAcceptsOnlyAccountsThatIredapdLooksUp(): void
    {
        // plugins/throttle.py matches the client IP, its IPv4 wildcards and
        // the address forms of get_policy_addresses_from_email().
        foreach (['user@domain.com', '@domain.com', '@.domain.com', '@.com', '@.', '192.0.2.1', '2001:db8::1', '192.0.2.*'] as $account) {
            $this->assertTrue(IredapdAccount::isThrottleAccount($account), $account);
        }
        foreach (['192.0.2.0/24', '2001:db8::/32', 'user@*', 'garbage-account', ''] as $account) {
            $this->assertFalse(IredapdAccount::isThrottleAccount($account), $account);
        }
    }

    public function testRejectsAnAccountThatIredapdCannotMatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        IredapdAccount::priority('garbage-account');
    }
}
