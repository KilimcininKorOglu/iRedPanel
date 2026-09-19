<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Parses the address lists that the panel writes to Postfix and mlmmj
 * (alias members and moderators, forwardings, list owners and subscribers).
 */
final class AddressList
{
    /**
     * Splits one address per line or comma, lowercased and without duplicates.
     *
     * @return list<string>
     * @throws \InvalidArgumentException carrying the first invalid address
     */
    public static function parse(string $raw): array
    {
        $addresses = [];
        foreach (preg_split('/[\r\n,]+/', $raw) ?: [] as $line) {
            $address = strtolower(trim($line));
            if ($address === '') {
                continue;
            }
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                throw new \InvalidArgumentException($address);
            }
            $addresses[$address] = true;
        }

        return array_keys($addresses);
    }
}
