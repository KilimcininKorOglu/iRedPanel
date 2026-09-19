<?php

declare(strict_types=1);

namespace Tests\Utils;

use App\Utils\IredapdList;
use PHPUnit\Framework\TestCase;

class IredapdListTest extends TestCase
{
    public function testRdnsNamesAcceptsHostsAndDomainSuffixes(): void
    {
        // wblist_rdns has a unique rdns column, so a repeated line must
        // collapse into one entry instead of failing the save.
        $names = IredapdList::rdnsNames([" .Dynamic.163data.com.cn\r", 'mail.example.com', '', '.dynamic.163data.com.cn']);

        $this->assertSame(['.dynamic.163data.com.cn', 'mail.example.com'], $names);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidRdnsNames(): array
    {
        return [
            'space' => ['not a host'],
            'single label' => ['localhost'],
            'leading hyphen' => ['-bad.example.com'],
            'empty label' => ['bad..example.com'],
            'address' => ['user@example.com'],
        ];
    }

    public function testGreylistSendersAcceptTheFormatsIredapdMatches(): void
    {
        // greylisting_whitelists is unique per (account, sender) with a
        // case-insensitive collation, so case variants must collapse.
        $senders = IredapdList::greylistSenders(['User@Example.com', '@example.org', '@.example.net', '192.0.2.1', '192.0.2.0/24', 'user@example.com']);

        $this->assertSame(['user@example.com', '@example.org', '@.example.net', '192.0.2.1', '192.0.2.0/24'], $senders);
    }

    public function testGreylistSendersRejectsText(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not an address');
        IredapdList::greylistSenders(['@example.org', 'not an address']);
    }

    /**
     * greylisting_whitelist_domains holds a bare domain name, without the '@'
     * that an Amavisd domain address carries.
     */
    public function testDomainsTakeTheBareNameOnly(): void
    {
        $this->assertSame(['example.com', 'mail.example.org'], IredapdList::domains(['Example.com', ' mail.example.org ', 'example.com']));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('@example.com');
        IredapdList::domains(['@example.com']);
    }

    public function testIpAddressesUseTheFormThatPostfixSends(): void
    {
        // iRedAPD looks up the client address exactly as Postfix reports it.
        $ips = IredapdList::ipAddresses(['203.0.113.5', '2001:DB8:0:0::1', '2001:db8::1', ' 203.0.113.5 ']);

        $this->assertSame(['203.0.113.5', '2001:db8::1'], $ips);
    }

    public function testIpAddressesRejectsAnInvalidLineInsteadOfDroppingIt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('203.0.113.256');
        IredapdList::ipAddresses(['203.0.113.5', '203.0.113.256']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidRdnsNames')]
    public function testRdnsNamesRejectsAnEntryIredapdCannotMatch(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($name);
        IredapdList::rdnsNames(['.ok.example.com', $name]);
    }
}
