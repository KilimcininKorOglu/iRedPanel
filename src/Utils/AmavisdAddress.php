<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Address formats that Amavisd (users, mailaddr) and the iRedAPD
 * amavisd_wblist plugin accept, with the same rules as iRedAdmin
 * (libs/iredutils.py is_valid_amavisd_address, get_wblist_address_type).
 */
class AmavisdAddress
{
    // Without the u modifier \w matches ASCII only, as in iRedAdmin (re.ASCII).
    // D stops $ from accepting a trailing newline.
    private const EMAIL = '/^[\w\-#][\w\-.+=\/&#]*@[\w\-][\w\-.]*\.[a-z0-9\-]{2,25}$/iD';
    private const DOMAIN = '/^[\w\-][\w\-.]*\.[a-z0-9\-]{2,25}$/iD';
    private const TLD = '/^[a-z0-9\-]{2,25}$/iD';
    private const WILDCARD_ADDR = '/^[\w\-][\w\-.+=]*@\*$/iD';
    private const WILDCARD_IPV4 = '/^[\d*]{1,3}\.[\d*]{1,3}\.[\d*]{1,3}\.[\d*]{1,3}$/D';

    /** Formats that a white/blacklist sender or recipient may use. */
    private const WBLIST_TYPES = [
        'email', 'domain', 'subdomain', 'tld_domain', 'catchall', 'ip', 'cidr_network', 'wildcard_addr',
    ];

    /**
     * Returns the format of an address, or null when Amavisd cannot use it.
     */
    public static function type(string $address): ?string
    {
        if (str_starts_with($address, '@')) {
            return self::domainType(substr($address, 1));
        }
        return self::hostType($address);
    }

    public static function isValidAccount(string $address): bool
    {
        return self::type($address) !== null;
    }

    public static function isValidWblistAddress(string $address): bool
    {
        return in_array(self::type($address), self::WBLIST_TYPES, true);
    }

    private static function domainType(string $domain): ?string
    {
        if ($domain === '.') {
            return 'catchall';
        }
        if (!str_starts_with($domain, '.')) {
            return preg_match(self::DOMAIN, $domain) === 1 ? 'domain' : null;
        }
        $parent = substr($domain, 1);
        if (preg_match(self::DOMAIN, $parent) === 1) {
            return 'subdomain';
        }
        return preg_match(self::TLD, $parent) === 1 ? 'tld_domain' : null;
    }

    private static function hostType(string $address): ?string
    {
        return match (true) {
            preg_match(self::EMAIL, $address) === 1 => 'email',
            filter_var($address, FILTER_VALIDATE_IP) !== false => 'ip',
            self::isCidrNetwork($address) => 'cidr_network',
            preg_match(self::WILDCARD_ADDR, $address) === 1 => 'wildcard_addr',
            preg_match(self::WILDCARD_IPV4, $address) === 1 => 'wildcard_ip',
            default => null,
        };
    }

    private static function isCidrNetwork(string $address): bool
    {
        $parts = explode('/', $address);
        if (count($parts) !== 2 || !ctype_digit($parts[1])) {
            return false;
        }
        $maxPrefix = filter_var($parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 128 : 32;
        return filter_var($parts[0], FILTER_VALIDATE_IP) !== false && (int) $parts[1] <= $maxPrefix;
    }
}
