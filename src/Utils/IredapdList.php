<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Validates and normalizes the lines of an iRedAPD list form. Every method
 * returns the unique lower-case entries and throws on the first invalid one.
 */
class IredapdList
{
    // A host name, or a domain suffix with a leading dot (iRedAPD wblist_rdns).
    private const RDNS = '/^\.?(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D';

    /**
     * @param string[] $lines
     * @return string[]
     * @throws \InvalidArgumentException with the invalid entry as message
     */
    public static function rdnsNames(array $lines): array
    {
        return self::normalize($lines, static fn (string $name): bool => preg_match(self::RDNS, $name) === 1);
    }

    /**
     * Greylisting whitelist senders: an address, a domain, a sub-domain, an IP
     * or a CIDR network. iRedAPD compares them in lower case.
     *
     * @param string[] $lines
     * @return string[]
     * @throws \InvalidArgumentException with the invalid entry as message
     */
    public static function greylistSenders(array $lines): array
    {
        return self::normalize($lines, [AmavisdAddress::class, 'isValidWblistAddress']);
    }

    /**
     * @param string[] $lines
     * @return string[]
     * @throws \InvalidArgumentException with the invalid entry as message
     */
    public static function ipAddresses(array $lines): array
    {
        $ips = self::normalize($lines, static fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP) !== false);
        // Postfix sends the compressed IPv6 form, and iRedAPD matches it exactly.
        $canonical = array_map(static fn (string $ip): string => (string) inet_ntop((string) inet_pton($ip)), $ips);
        return array_values(array_unique($canonical));
    }

    /**
     * @param string[] $lines
     * @param callable(string): bool $isValid
     * @return string[]
     */
    private static function normalize(array $lines, callable $isValid): array
    {
        $entries = [];
        foreach ($lines as $line) {
            $entry = strtolower(trim($line));
            if ($entry === '') {
                continue;
            }
            if (!$isValid($entry)) {
                throw new \InvalidArgumentException(trim($line));
            }
            if (!in_array($entry, $entries, true)) {
                $entries[] = $entry;
            }
        }
        return $entries;
    }
}
