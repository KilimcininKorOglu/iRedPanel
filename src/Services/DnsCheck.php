<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DnsCheckRow;
use App\Utils\DnsLookup;
use App\Utils\DnsRecords;

/**
 * Checks the DNS records that a mail domain needs: MX, the addresses of the MX
 * hosts, SPF, DKIM, DMARC and the PTR records of the MX addresses.
 *
 * The check reads DNS only. The DKIM private key lives in `/var/lib/dkim/` on the
 * mail server and in no database, so the DKIM check says whether a DKIM record
 * exists and parses, never whether it matches the key of the server.
 */
final class DnsCheck
{
    /** The checks in the order the page shows them. */
    public const CHECKS = ['mx', 'a', 'spf', 'dkim', 'dmarc', 'ptr'];

    /** Each MX host costs two more queries, so a long MX list stops here. */
    private const MAX_HOSTS = 5;

    /** Worst wins when one check answers for several hosts. */
    private const SEVERITY = [
        DnsCheckRow::OK => 0,
        DnsCheckRow::WARN => 1,
        DnsCheckRow::FAIL => 2,
    ];

    /** iRedMail signs with this selector, and a value that is no DNS label falls back to it. */
    private const DEFAULT_SELECTOR = 'dkim';

    private readonly DnsRecords $records;

    private readonly string $selector;

    /** The moment every remaining check stops and answers UNKNOWN. */
    private float $deadline = 0.0;

    /**
     * @param float $budgetSeconds total time for all queries of one run. A resolver
     *                             that answers nothing costs the SystemDnsLookup
     *                             timeout per query, so the page needs a ceiling.
     */
    public function __construct(
        DnsLookup $lookup,
        string $dkimSelector = self::DEFAULT_SELECTOR,
        private readonly float $budgetSeconds = 8.0,
    ) {
        $this->records = new DnsRecords($lookup);
        $selector = trim($dkimSelector, " \t.");
        $this->selector = preg_match('/^[A-Za-z0-9]([A-Za-z0-9._-]*[A-Za-z0-9])?$/', $selector) === 1
            ? $selector
            : self::DEFAULT_SELECTOR;
    }

    /**
     * Every check of one domain, in CHECKS order.
     *
     * @return list<DnsCheckRow>
     */
    public function run(string $domain): array
    {
        $domain = DnsRecords::name($domain);
        $this->deadline = microtime(true) + $this->budgetSeconds;

        $hosts = array_slice($this->records->mxHosts($domain), 0, self::MAX_HOSTS);
        [$addressRow, $addresses] = $this->addressCheck($hosts);

        return [
            $this->mxCheck($hosts),
            $addressRow,
            $this->guard('spf', fn (): DnsCheckRow => $this->spfCheck($domain)),
            $this->guard('dkim', fn (): DnsCheckRow => $this->dkimCheck($domain)),
            $this->guard('dmarc', fn (): DnsCheckRow => $this->dmarcCheck($domain)),
            $this->ptrCheck($addresses),
        ];
    }

    /** The name that the DKIM check asks, so the page can show it. */
    public function dkimName(string $domain): string
    {
        return $this->selector . '._domainkey.' . DnsRecords::name($domain);
    }

    /**
     * Runs one check, or answers UNKNOWN when the time budget of the run is spent.
     *
     * @param callable(): DnsCheckRow $check
     */
    private function guard(string $key, callable $check): DnsCheckRow
    {
        return $this->expired() ? new DnsCheckRow($key, DnsCheckRow::UNKNOWN, [], 'msg_timeout') : $check();
    }

    private function expired(): bool
    {
        return microtime(true) >= $this->deadline;
    }

    /**
     * @param list<string> $hosts
     */
    private function mxCheck(array $hosts): DnsCheckRow
    {
        if ($hosts === []) {
            return $this->expired()
                ? new DnsCheckRow('mx', DnsCheckRow::UNKNOWN, [], 'msg_timeout')
                : new DnsCheckRow('mx', DnsCheckRow::FAIL, [], 'msg_mx_missing');
        }

        return new DnsCheckRow('mx', DnsCheckRow::OK, $hosts, 'msg_mx_found');
    }

    /**
     * The A and AAAA records of every MX host. A host without any address stops the
     * mail, and a host with an AAAA record only reaches no IPv4 sender.
     *
     * @param list<string> $hosts
     * @return array{0: DnsCheckRow, 1: list<array{host: string, ip: string}>} the row and the IPv4 addresses
     */
    private function addressCheck(array $hosts): array
    {
        if ($hosts === []) {
            return [new DnsCheckRow('a', DnsCheckRow::UNKNOWN, [], $this->expired() ? 'msg_timeout' : 'msg_needs_mx'), []];
        }

        $values = [];
        $addresses = [];
        $status = DnsCheckRow::OK;
        foreach ($hosts as $host) {
            if ($this->expired()) {
                return [new DnsCheckRow('a', DnsCheckRow::UNKNOWN, $values, 'msg_timeout'), $addresses];
            }
            $ipv4 = $this->records->ipv4($host);
            $ipv6 = $this->records->ipv6($host);
            $status = $this->worse($status, $this->addressStatus($ipv4, $ipv6));
            $values[] = $host . ': ' . ($ipv4 === [] && $ipv6 === [] ? '-' : implode(', ', [...$ipv4, ...$ipv6]));
            foreach ($ipv4 as $ip) {
                $addresses[] = ['host' => $host, 'ip' => $ip];
            }
        }

        return [new DnsCheckRow('a', $status, $values, 'msg_a_' . $status), $addresses];
    }

    /**
     * @param list<string> $ipv4
     * @param list<string> $ipv6
     */
    private function addressStatus(array $ipv4, array $ipv6): string
    {
        if ($ipv4 === [] && $ipv6 === []) {
            return DnsCheckRow::FAIL;
        }

        return $ipv4 === [] ? DnsCheckRow::WARN : DnsCheckRow::OK;
    }

    private function spfCheck(string $domain): DnsCheckRow
    {
        $records = $this->records->txt($domain, 'v=spf1');
        if ($records === []) {
            return new DnsCheckRow('spf', DnsCheckRow::FAIL, [], 'msg_spf_missing');
        }
        if (count($records) > 1) {
            // RFC 7208 section 3.2: a second SPF record makes the evaluation permerror.
            return new DnsCheckRow('spf', DnsCheckRow::WARN, $records, 'msg_spf_many');
        }

        return new DnsCheckRow('spf', DnsCheckRow::OK, $records, 'msg_spf_found');
    }

    private function dkimCheck(string $domain): DnsCheckRow
    {
        $name = $this->dkimName($domain);
        $records = $this->records->txt($name, 'v=dkim1');
        if ($records === []) {
            return new DnsCheckRow('dkim', DnsCheckRow::FAIL, [], 'msg_dkim_missing', ['name' => $name]);
        }
        // An empty p= tag revokes the key: Amavisd still signs, every verifier fails.
        if (preg_match('/\bp=\s*[A-Za-z0-9+\/=]+/', $records[0]) !== 1) {
            return new DnsCheckRow('dkim', DnsCheckRow::WARN, $records, 'msg_dkim_revoked', ['name' => $name]);
        }

        return new DnsCheckRow('dkim', DnsCheckRow::OK, $records, 'msg_dkim_found', ['name' => $name]);
    }

    private function dmarcCheck(string $domain): DnsCheckRow
    {
        $records = $this->records->txt('_dmarc.' . $domain, 'v=dmarc1');
        if ($records === []) {
            return new DnsCheckRow('dmarc', DnsCheckRow::WARN, [], 'msg_dmarc_missing');
        }
        if (preg_match('/\bp=\s*(none|quarantine|reject)\b/i', $records[0], $match) !== 1) {
            return new DnsCheckRow('dmarc', DnsCheckRow::FAIL, $records, 'msg_dmarc_invalid');
        }

        return new DnsCheckRow('dmarc', DnsCheckRow::OK, $records, 'msg_dmarc_found', ['policy' => strtolower($match[1])]);
    }

    /**
     * The PTR record of every MX address, and whether the name points back to the
     * same address. A receiving server that finds no PTR name often refuses the mail.
     *
     * @param list<array{host: string, ip: string}> $addresses
     */
    private function ptrCheck(array $addresses): DnsCheckRow
    {
        if ($addresses === []) {
            return new DnsCheckRow('ptr', DnsCheckRow::UNKNOWN, [], $this->expired() ? 'msg_timeout' : 'msg_needs_mx');
        }

        $values = [];
        $status = DnsCheckRow::OK;
        foreach ($addresses as $address) {
            if ($this->expired()) {
                return new DnsCheckRow('ptr', DnsCheckRow::UNKNOWN, $values, 'msg_timeout');
            }
            $name = $this->records->ptrName($address['ip']);
            $status = $this->worse($status, $name === '' ? DnsCheckRow::FAIL : $this->ptrStatus($name, $address['ip']));
            $values[] = $address['ip'] . ' -> ' . ($name === '' ? '-' : $name);
        }

        return new DnsCheckRow('ptr', $status, $values, 'msg_ptr_' . $status);
    }

    /** Forward-confirmed reverse DNS: the PTR name must resolve back to the address. */
    private function ptrStatus(string $name, string $ip): string
    {
        return in_array($ip, $this->records->ipv4($name), true) ? DnsCheckRow::OK : DnsCheckRow::WARN;
    }

    private function worse(string $current, string $next): string
    {
        return (self::SEVERITY[$next] ?? 0) > (self::SEVERITY[$current] ?? 0) ? $next : $current;
    }
}
