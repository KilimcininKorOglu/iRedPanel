<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Reads the record kinds that a mail domain check needs and hands back plain
 * strings. It holds no judgement about a record; App\Services\DnsCheck does that.
 */
final class DnsRecords
{
    public function __construct(private readonly DnsLookup $lookup)
    {
    }

    /**
     * The MX hosts of a domain, the lowest preference first, each name once.
     *
     * @return list<string>
     */
    public function mxHosts(string $domain): array
    {
        $records = $this->lookup->records($domain, DNS_MX);
        usort($records, static fn (array $a, array $b): int => ((int) ($a['pri'] ?? 0)) <=> ((int) ($b['pri'] ?? 0)));

        $hosts = [];
        foreach ($records as $record) {
            $host = self::name((string) ($record['target'] ?? ''));
            if ($host !== '' && !in_array($host, $hosts, true)) {
                $hosts[] = $host;
            }
        }

        return $hosts;
    }

    /**
     * The IPv4 addresses of a host.
     *
     * @return list<string>
     */
    public function ipv4(string $host): array
    {
        return $this->addresses($host, DNS_A, 'ip');
    }

    /**
     * The IPv6 addresses of a host.
     *
     * @return list<string>
     */
    public function ipv6(string $host): array
    {
        return $this->addresses($host, DNS_AAAA, 'ipv6');
    }

    /**
     * The TXT records of a name that start with a tag, for example `v=spf1`.
     *
     * @return list<string>
     */
    public function txt(string $name, string $tag): array
    {
        $values = [];
        foreach ($this->lookup->records($name, DNS_TXT) as $record) {
            $text = trim((string) ($record['txt'] ?? ''));
            if ($text !== '' && str_starts_with(strtolower($text), $tag)) {
                $values[] = $text;
            }
        }

        return $values;
    }

    /** The first PTR name of an IPv4 address, or an empty string when none answers. */
    public function ptrName(string $ip): string
    {
        $reverse = implode('.', array_reverse(explode('.', $ip))) . '.in-addr.arpa';
        foreach ($this->lookup->records($reverse, DNS_PTR) as $record) {
            $target = self::name((string) ($record['target'] ?? ''));
            if ($target !== '') {
                return $target;
            }
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function addresses(string $host, int $type, string $field): array
    {
        $addresses = [];
        foreach ($this->lookup->records($host, $type) as $record) {
            $address = (string) ($record[$field] ?? '');
            if ($address !== '') {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }

    /** A DNS name in the form the checks compare: lower case, no trailing dot. */
    public static function name(string $value): string
    {
        return strtolower(trim($value, " \t."));
    }
}
