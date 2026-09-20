<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * One DNS query. The interface exists so that a test drives the DNS checks with
 * stored answers instead of the network.
 */
interface DnsLookup
{
    /**
     * The records of one name, in the shape that `dns_get_record()` returns.
     *
     * @param int $type one of the DNS_* constants
     * @return array<int, array<string, mixed>> an empty array when the query fails
     */
    public function records(string $name, int $type): array;
}
