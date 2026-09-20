<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Asks the resolver of the server. `dns_get_record()` warns and answers false when
 * the name does not exist or the resolver fails, so both become an empty array.
 */
final class SystemDnsLookup implements DnsLookup
{
    /**
     * `dns_get_record()` takes no timeout, so the resolver options of the C library
     * carry it. Without this a name that no resolver answers costs 8 seconds per
     * query. The C library reads the value at the FIRST query of the process, so a
     * worker that already resolved a name keeps its earlier options; the time budget
     * of DnsCheck is the ceiling that always holds.
     *
     * @param int $timeout seconds the resolver waits for one answer
     * @param int $attempts queries the resolver sends before it gives up
     */
    public function __construct(int $timeout = 2, int $attempts = 1)
    {
        putenv("RES_OPTIONS=timeout:{$timeout} attempts:{$attempts}");
    }

    public function records(string $name, int $type): array
    {
        $records = @dns_get_record($name, $type);

        return $records === false ? [] : $records;
    }
}
