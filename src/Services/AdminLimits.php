<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidInputException;
use App\Middleware;
use App\Models\Admin;
use App\Repositories\RepositoryFactory;

/**
 * The creation limits of a domain admin, as in iRedAdmin-Pro. The counts cover every
 * domain that the admin manages; the quota limit bounds the sum of the mailbox quotas.
 * A limit of -1 is unlimited, and no limit binds a global admin.
 */
final class AdminLimits
{
    /** Resource kind => Admin limit property and error message key. */
    private const LIMITS = [
        'domains' => ['createMaxDomains', 'domain.msg_creation_limit'],
        'users' => ['createMaxUsers', 'user.msg_creation_limit'],
        'aliases' => ['createMaxAliases', 'alias.msg_creation_limit'],
        'lists' => ['createMaxLists', 'mlist.msg_creation_limit'],
    ];

    /**
     * Returns why the admin must not create one more resource of the kind, or null.
     *
     * @param array<string, int> $counts getAdminResourceCounts() of the admin
     */
    public static function countError(Admin $admin, array $counts, string $kind): ?InvalidInputException
    {
        [$property, $messageKey] = self::LIMITS[$kind];
        $limit = $admin->{$property};
        if ($admin->isGlobalAdmin || $limit < 0 || ($counts[$kind] ?? 0) < $limit) {
            return null;
        }

        return new InvalidInputException("{$kind} creation limit reached ({$limit})", $messageKey, ['limit' => $limit]);
    }

    /**
     * Returns why a mailbox quota must not change from $oldMb to $newMb (both 0 for
     * unlimited; $oldMb is 0 for a new mailbox), or null. A lower quota is always allowed.
     */
    public static function quotaError(Admin $admin, int $usedMb, int $oldMb, int $newMb): ?InvalidInputException
    {
        $max = $admin->createMaxQuota;
        if ($admin->isGlobalAdmin || $max < 0 || ($newMb > 0 && $newMb <= $oldMb)) {
            return null;
        }
        if ($newMb === 0) {
            return new InvalidInputException('the admin quota limit needs a mailbox quota', 'user.msg_admin_quota_unlimited');
        }
        if ($usedMb - $oldMb + $newMb > $max) {
            return new InvalidInputException(
                'the admin quota limit is reached',
                'user.msg_admin_quota_limit',
                ['used' => $usedMb, 'max' => $max],
            );
        }

        return null;
    }

    /**
     * @throws InvalidInputException when the logged-in admin reached the limit of the kind
     */
    public static function assertCanCreate(string $kind): void
    {
        $admin = self::sessionAdmin();
        self::throwIf($admin === null ? null : self::countError($admin, self::counts($admin), $kind));
    }

    /**
     * @throws InvalidInputException when the logged-in admin must not give a mailbox this quota
     */
    public static function assertQuota(int $oldMb, int $newMb): void
    {
        $admin = self::sessionAdmin();
        self::throwIf($admin === null ? null : self::quotaError($admin, self::counts($admin)['quotaMb'], $oldMb, $newMb));
    }

    /**
     * Whether the logged-in admin may create a domain: a global admin, or a domain admin
     * with domain creation allowed.
     */
    public static function canCreateDomain(): bool
    {
        return Middleware::isGlobalAdmin() || self::sessionAdmin()?->createNewDomains === true;
    }

    private static function throwIf(?InvalidInputException $error): void
    {
        if ($error !== null) {
            throw $error;
        }
    }

    /**
     * @return Admin|null the logged-in domain admin, or null for a global admin
     */
    private static function sessionAdmin(): ?Admin
    {
        if (Middleware::isGlobalAdmin()) {
            return null;
        }

        return RepositoryFactory::getAdminRepository()->getAdmin((string) ($_SESSION['email'] ?? ''));
    }

    /**
     * @return array<string, int>
     */
    private static function counts(Admin $admin): array
    {
        return RepositoryFactory::getAdminRepository()->getAdminResourceCounts($admin->username);
    }
}
