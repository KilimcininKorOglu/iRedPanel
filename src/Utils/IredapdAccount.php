<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Priorities of iRedAPD throttle and greylisting accounts. iRedAPD sorts the
 * matching rows by priority, highest first, and uses the first one.
 */
class IredapdAccount
{
    /** iRedAPD libs/__init__.py ACCOUNT_PRIORITIES, keyed by AmavisdAddress::type(). */
    private const PRIORITIES = [
        'email' => 100,
        'wildcard_addr' => 90,
        'ip' => 80,
        'wildcard_ip' => 70,
        'cidr_network' => 70,
        'domain' => 60,
        'subdomain' => 50,
        'tld_domain' => 40,
        'catchall' => 0,
    ];

    /**
     * @throws \InvalidArgumentException for an account that iRedAPD cannot match
     */
    public static function priority(string $account): int
    {
        $type = AmavisdAddress::type($account);
        if ($type === null) {
            throw new \InvalidArgumentException("Invalid iRedAPD account: {$account}");
        }
        return self::PRIORITIES[$type];
    }
}
