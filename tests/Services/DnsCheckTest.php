<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Models\DnsCheckRow;
use App\Services\DnsCheck;
use App\Utils\DnsLookup;
use PHPUnit\Framework\TestCase;

/**
 * Drives the DNS checks with stored answers, so no test reaches the network.
 */
class DnsCheckTest extends TestCase
{
    /** A complete, healthy zone of example.com. */
    private const HEALTHY = [
        'example.com|MX' => [['pri' => 10, 'target' => 'mx.example.com']],
        'mx.example.com|A' => [['ip' => '198.51.100.7']],
        'mx.example.com|AAAA' => [],
        'example.com|TXT' => [['txt' => 'v=spf1 mx -all']],
        'dkim._domainkey.example.com|TXT' => [['txt' => 'v=DKIM1; k=rsa; p=MIIBIjANBg']],
        '_dmarc.example.com|TXT' => [['txt' => 'v=DMARC1; p=quarantine; rua=mailto:a@example.com']],
        '7.100.51.198.in-addr.arpa|PTR' => [['target' => 'mx.example.com']],
    ];

    public function testAHealthyZonePassesEveryCheck(): void
    {
        $rows = $this->check(self::HEALTHY);

        $this->assertSame(DnsCheck::CHECKS, array_keys($rows));
        foreach ($rows as $key => $row) {
            $this->assertSame(DnsCheckRow::OK, $row->status, "check {$key}");
        }
    }

    public function testAnEmptyZoneFailsEveryCheckThatCanRun(): void
    {
        $rows = $this->check([]);

        $this->assertSame(DnsCheckRow::FAIL, $rows['mx']->status);
        $this->assertSame(DnsCheckRow::FAIL, $rows['spf']->status);
        $this->assertSame(DnsCheckRow::FAIL, $rows['dkim']->status);
        // A missing DMARC record is a gap, not a broken record.
        $this->assertSame(DnsCheckRow::WARN, $rows['dmarc']->status);
        // Without an MX host there is no address and no PTR name to ask for.
        $this->assertSame(DnsCheckRow::UNKNOWN, $rows['a']->status);
        $this->assertSame(DnsCheckRow::UNKNOWN, $rows['ptr']->status);
    }

    public function testAnMxHostWithoutAnAddressFails(): void
    {
        $rows = $this->check(['example.com|MX' => [['pri' => 10, 'target' => 'mx.example.com']]]);

        $this->assertSame(DnsCheckRow::OK, $rows['mx']->status);
        $this->assertSame(DnsCheckRow::FAIL, $rows['a']->status);
        $this->assertSame(['mx.example.com: -'], $rows['a']->values);
    }

    public function testAnMxHostWithAnIpv6AddressOnlyWarns(): void
    {
        $rows = $this->check([
            'example.com|MX' => [['pri' => 10, 'target' => 'mx.example.com']],
            'mx.example.com|AAAA' => [['ipv6' => '2001:db8::7']],
        ]);

        $this->assertSame(DnsCheckRow::WARN, $rows['a']->status);
        $this->assertSame(['mx.example.com: 2001:db8::7'], $rows['a']->values);
        // The PTR check asks IPv4 addresses only, so it has nothing to ask.
        $this->assertSame(DnsCheckRow::UNKNOWN, $rows['ptr']->status);
    }

    public function testASecondSpfRecordWarns(): void
    {
        $rows = $this->check(['example.com|TXT' => [
            ['txt' => 'v=spf1 mx -all'],
            ['txt' => 'v=spf1 include:other.example -all'],
            ['txt' => 'google-site-verification=abc'],
        ]]);

        $this->assertSame(DnsCheckRow::WARN, $rows['spf']->status);
        $this->assertCount(2, $rows['spf']->values);
    }

    public function testARevokedDkimKeyWarns(): void
    {
        $rows = $this->check(['dkim._domainkey.example.com|TXT' => [['txt' => 'v=DKIM1; k=rsa; p=']]]);

        $this->assertSame(DnsCheckRow::WARN, $rows['dkim']->status);
    }

    public function testTheSelectorDecidesTheDkimName(): void
    {
        $check = new DnsCheck(new FakeDnsLookup(['mail._domainkey.example.com|TXT' => [['txt' => 'v=DKIM1; p=AAAA']]]), 'mail');
        $rows = $this->byKey($check->run('example.com'));

        $this->assertSame('mail._domainkey.example.com', $check->dkimName('example.com'));
        $this->assertSame(DnsCheckRow::OK, $rows['dkim']->status);
    }

    /**
     * A selector that is no DNS label would build a name that no resolver answers,
     * so the check asks the iRedMail default instead.
     */
    public function testAnInvalidSelectorFallsBackToTheDefault(): void
    {
        $check = new DnsCheck(new FakeDnsLookup(self::HEALTHY), 'not a label!');

        $this->assertSame('dkim._domainkey.example.com', $check->dkimName('example.com'));
    }

    public function testADmarcRecordWithoutAPolicyFails(): void
    {
        $rows = $this->check(['_dmarc.example.com|TXT' => [['txt' => 'v=DMARC1; rua=mailto:a@example.com']]]);

        $this->assertSame(DnsCheckRow::FAIL, $rows['dmarc']->status);
    }

    public function testTheDmarcPolicyReachesTheMessage(): void
    {
        $rows = $this->check(self::HEALTHY);

        $this->assertSame(['policy' => 'quarantine'], $rows['dmarc']->params);
    }

    public function testAPtrNameThatPointsElsewhereWarns(): void
    {
        $zone = self::HEALTHY;
        $zone['7.100.51.198.in-addr.arpa|PTR'] = [['target' => 'other.example.net']];
        $zone['other.example.net|A'] = [['ip' => '203.0.113.9']];

        $rows = $this->check($zone);

        $this->assertSame(DnsCheckRow::WARN, $rows['ptr']->status);
        $this->assertSame(['198.51.100.7 -> other.example.net'], $rows['ptr']->values);
    }

    public function testAMissingPtrRecordFails(): void
    {
        $zone = self::HEALTHY;
        unset($zone['7.100.51.198.in-addr.arpa|PTR']);

        $rows = $this->check($zone);

        $this->assertSame(DnsCheckRow::FAIL, $rows['ptr']->status);
    }

    public function testTheMxHostsKeepTheirPreferenceOrder(): void
    {
        $rows = $this->check(['example.com|MX' => [
            ['pri' => 20, 'target' => 'backup.example.com'],
            ['pri' => 10, 'target' => 'mx.example.com'],
        ]]);

        $this->assertSame(['mx.example.com', 'backup.example.com'], $rows['mx']->values);
    }

    /**
     * A long MX list stops after five hosts, because each host costs two more queries.
     */
    public function testAtMostFiveMxHostsAreChecked(): void
    {
        $records = [];
        foreach (range(1, 8) as $index) {
            $records[] = ['pri' => $index, 'target' => "mx{$index}.example.com"];
        }

        $rows = $this->check(['example.com|MX' => $records]);

        $this->assertCount(5, $rows['mx']->values);
    }

    /**
     * A spent time budget stops the remaining queries instead of letting a silent
     * resolver hold the page for its own timeout on every check.
     */
    public function testASpentBudgetStopsTheRemainingChecks(): void
    {
        $check = new DnsCheck(new FakeDnsLookup(self::HEALTHY), 'dkim', 0.0);
        $rows = $this->byKey($check->run('example.com'));

        foreach (['spf', 'dkim', 'dmarc', 'ptr'] as $key) {
            $this->assertSame(DnsCheckRow::UNKNOWN, $rows[$key]->status, "check {$key}");
            $this->assertSame('msg_timeout', $rows[$key]->detail, "check {$key}");
        }
        // The MX query runs before the deadline is consulted, so its answer stands.
        $this->assertSame(DnsCheckRow::OK, $rows['mx']->status);
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $zone
     * @return array<string, DnsCheckRow>
     */
    private function check(array $zone): array
    {
        return $this->byKey((new DnsCheck(new FakeDnsLookup($zone)))->run('example.com'));
    }

    /**
     * @param list<DnsCheckRow> $rows
     * @return array<string, DnsCheckRow>
     */
    private function byKey(array $rows): array
    {
        return array_combine(array_map(static fn (DnsCheckRow $row): string => $row->key, $rows), $rows);
    }
}

/**
 * Answers from a stored zone. The key is `<name>|<type name>`, because the DNS_*
 * constants of PHP are bit masks and not the record type numbers.
 */
final class FakeDnsLookup implements DnsLookup
{
    private const TYPES = [DNS_A => 'A', DNS_AAAA => 'AAAA', DNS_MX => 'MX', DNS_TXT => 'TXT', DNS_PTR => 'PTR'];

    /** @param array<string, array<int, array<string, mixed>>> $zone */
    public function __construct(private readonly array $zone)
    {
    }

    public function records(string $name, int $type): array
    {
        return $this->zone[$name . '|' . (self::TYPES[$type] ?? $type)] ?? [];
    }
}
