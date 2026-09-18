<?php

declare(strict_types=1);

namespace Tests\Utils;

use App\Utils\AmavisdAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AmavisdAddressTest extends TestCase
{
    /** @return array<string, array{string, ?string}> */
    public static function addresses(): array
    {
        // The expected types follow the doctests of iRedAdmin is_valid_amavisd_address.
        return [
            'email' => ['user@domain.com', 'email'],
            'domain' => ['@domain.com', 'domain'],
            'subdomain' => ['@.sub.domain.com', 'subdomain'],
            'tld domain' => ['@.io', 'tld_domain'],
            'catchall' => ['@.', 'catchall'],
            'ipv4' => ['192.168.1.1', 'ip'],
            'ipv6' => ['::1', 'ip'],
            'ipv4 network' => ['192.168.1.0/24', 'cidr_network'],
            'ipv6 network' => ['2620:0:2d0:200::7/128', 'cidr_network'],
            'wildcard address' => ['user@*', 'wildcard_addr'],
            'wildcard ipv4' => ['192.168.1.*', 'wildcard_ip'],
            'words' => ['not an address', null],
            'empty' => ['', null],
            'domain without @' => ['domain.com', null],
            'trailing newline' => ["user@domain.com\n", null],
            'ipv4 prefix too long' => ['192.168.1.0/33', null],
        ];
    }

    #[DataProvider('addresses')]
    public function testDetectsTheAddressType(string $address, ?string $type): void
    {
        $this->assertSame($type, AmavisdAddress::type($address));
    }

    public function testWblistRejectsAWildcardIp(): void
    {
        // iRedAdmin get_wblist_address_type has no wildcard IP format.
        $this->assertTrue(AmavisdAddress::isValidAccount('192.168.1.*'));
        $this->assertFalse(AmavisdAddress::isValidWblistAddress('192.168.1.*'));
    }
}
