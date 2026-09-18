<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DomainOwnershipRepositoryInterface;

/**
 * Starts the ownership verification of a domain that an admin tries to
 * create while verification is required.
 */
class DomainOwnershipService
{
    private const CODE_PREFIX = 'iredpanel-domain-verification-';

    /**
     * Returns the value of the DNS TXT record that proves ownership of the
     * domain. The first call for a domain stores a pending verification.
     */
    public static function pendingCode(DomainOwnershipRepositoryInterface $repo, string $domain, string $admin): string
    {
        $code = $repo->getVerifyCode($domain);
        if ($code !== null) {
            return $code;
        }

        $code = self::CODE_PREFIX . bin2hex(random_bytes(16));
        // expire 0: the pending record stays until it is verified.
        $repo->addPendingDomain($admin, $domain, $code, 0);
        return $code;
    }
}
