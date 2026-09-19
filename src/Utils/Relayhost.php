<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Postfix next-hop syntax for `sender_dependent_relayhost_maps`: `host`,
 * `host:port`, `[host]` or `[host]:port`. Square brackets skip the MX lookup
 * and are required around an IPv6 address.
 */
class Relayhost
{
    private const HOSTNAME = '/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/i';

    public static function isValid(string $relayhost): bool
    {
        if (!preg_match('/^(\[(?<bracketed>[^\]]+)\]|(?<plain>[^\[\]:]+))(:(?<port>\d{1,5}))?$/', $relayhost, $m)) {
            return false;
        }

        $port = $m['port'] ?? '';
        if ($port !== '' && ((int) $port < 1 || (int) $port > 65535)) {
            return false;
        }

        $bracketed = $m['bracketed'] ?? '';
        if ($bracketed !== '') {
            return self::isHost($bracketed) || filter_var($bracketed, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        }

        return self::isHost($m['plain']);
    }

    private static function isHost(string $host): bool
    {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            || preg_match(self::HOSTNAME, $host) === 1;
    }
}
