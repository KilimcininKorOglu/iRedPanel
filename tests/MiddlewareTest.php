<?php

declare(strict_types=1);

namespace Tests;

use App\Middleware;
use PHPUnit\Framework\TestCase;

class MiddlewareTest extends TestCase
{
    public function testAllowsAnAddressInsideACidrRange(): void
    {
        $this->assertTrue(Middleware::isIpAllowed('192.168.164.1', '10.0.0.0/8, 192.168.164.0/24'));
    }

    public function testRejectsAnAddressOutsideEveryRange(): void
    {
        $this->assertFalse(Middleware::isIpAllowed('192.168.164.1', '10.0.0.0/8,172.16.0.5'));
    }

    public function testTrailingCommaDoesNotAllowEveryAddress(): void
    {
        // An empty entry used to match every client, so a typo removed the restriction.
        $this->assertFalse(Middleware::isIpAllowed('192.168.164.1', '10.99.0.0/16,'));
    }

    public function testListWithoutEntriesMeansNoRestriction(): void
    {
        $this->assertTrue(Middleware::isIpAllowed('192.168.164.1', ' , '));
    }

    public function testInvalidPrefixLengthIsIgnoredWithoutError(): void
    {
        // A prefix above 32 used to raise ArithmeticError on every request.
        $this->assertFalse(Middleware::isIpAllowed('192.168.164.1', '192.168.164.0/33'));
        $this->assertTrue(Middleware::isIpAllowed('192.168.164.1', '192.168.164.0/33,192.168.164.1'));
    }

    public function testZeroPrefixMatchesEveryIpv4Address(): void
    {
        $this->assertTrue(Middleware::isIpAllowed('203.0.113.9', '0.0.0.0/0'));
    }

    public function testExactIpv6AddressMatches(): void
    {
        $this->assertTrue(Middleware::isIpAllowed('2001:db8::1', '2001:db8::1'));
        $this->assertFalse(Middleware::isIpAllowed('2001:db8::2', '2001:db8::1'));
    }

    public function testValidatesRangeEntries(): void
    {
        $this->assertTrue(Middleware::isValidIpRange('10.0.0.0/8'));
        $this->assertTrue(Middleware::isValidIpRange('192.168.1.10'));
        $this->assertTrue(Middleware::isValidIpRange('2001:db8::1'));
        $this->assertFalse(Middleware::isValidIpRange('10.0.0.0/33'));
        $this->assertFalse(Middleware::isValidIpRange('10.0.0.0/-1'));
        $this->assertFalse(Middleware::isValidIpRange('10.0.0.0/'));
        $this->assertFalse(Middleware::isValidIpRange('office'));
        $this->assertFalse(Middleware::isValidIpRange('2001:db8::/32'));
    }
}
