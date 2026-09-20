<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ExpiredAccountRepositoryInterface;
use App\Repositories\RepositoryFactory;

/**
 * Disables the accounts whose expiry date has passed. No open source iRedMail
 * component reads the expiry date, so `cli/disableExpiredAccounts.php` runs this
 * service from cron.
 *
 * The service only disables. It never enables an account again, because it cannot
 * know why an account is disabled.
 */
final class AccountExpiry
{
    public function __construct(private readonly ExpiredAccountRepositoryInterface $expired)
    {
    }

    public static function create(): self
    {
        return new self(RepositoryFactory::getExpiredAccountRepository());
    }

    /**
     * Disables every expired account that is still active.
     *
     * @param bool $dryRun lists the accounts and writes nothing
     * @param ?int $now unix timestamp, the current time when null
     * @return array{mailboxes: list<string>, domains: list<string>, admins: list<string>}
     */
    public function disableExpired(bool $dryRun, ?int $now = null): array
    {
        $found = [
            'mailboxes' => $this->expired->expiredMailboxes($now),
            'domains' => $this->expired->expiredDomains($now),
            'admins' => $this->expired->expiredAdmins($now),
        ];
        if ($dryRun) {
            return $found;
        }

        foreach ($found['mailboxes'] as $address) {
            $this->disableMailbox($address);
        }
        foreach ($found['domains'] as $domainName) {
            RepositoryFactory::getDomainRepository()->enableDisableDomain($domainName, false);
            ActivityLogger::log('disable', $domainName, $domainName, "Domain disabled: expiry date passed");
        }
        foreach ($found['admins'] as $address) {
            RepositoryFactory::getAdminRepository()->enableDisableAdmin($address, false);
            ActivityLogger::log('disable', '', $address, "Admin disabled: expiry date passed");
        }

        return $found;
    }

    private function disableMailbox(string $address): void
    {
        [$uid, $domainName] = explode('@', $address, 2) + ['', ''];
        $users = RepositoryFactory::getUserRepository();
        $user = $users->getUser($domainName, $uid);
        if ($user === null || !$user->accountStatus) {
            return;
        }

        $user->accountStatus = false;
        $users->updateUser($domainName, $user);
        ActivityLogger::log('disable', $domainName, $address, "Mailbox disabled: expiry date passed");
    }
}
