<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BackendConnectionException;
use App\Models\DomainSettings;
use App\Models\ProfileToggles;
use App\Models\User;
use App\Repositories\RepositoryFactory;

/**
 * Login and account context of a mailbox user in self-service. A user logs in when the
 * mailbox and its domain are active and the domain has self-service on.
 */
final class SelfService
{
    /** Self-service page slug => the user preference that opens it (ProfileToggles::USER_PREFERENCES). */
    public const PAGES = [
        'profile' => 'personal_info',
        'password' => 'password',
        'forwarding' => 'forwarding',
        'wblist' => 'wblist',
        'spam-policy' => 'spampolicy',
        'quarantine' => 'quarantine',
        'received' => 'received',
        'sent' => 'sent',
    ];

    /** Pages that read or write Amavisd data. */
    public const AMAVISD_PAGES = ['wblist', 'spam-policy', 'quarantine', 'received', 'sent'];

    /**
     * @throws BackendConnectionException when the backend is not reachable
     */
    public static function authenticate(string $email, string $password): bool
    {
        $email = strtolower(trim($email));
        if ($password === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }
        [$uid, $domainName] = explode('@', $email, 2);

        $domain = RepositoryFactory::getDomainRepository()->getDomain($domainName);
        if ($domain === null || !$domain->active || !DomainSettings::fromSettingsString($domain->settings)->selfService()) {
            return false;
        }
        $user = RepositoryFactory::getUserRepository()->getUser($domainName, $uid);
        if ($user === null || !$user->accountStatus) {
            return false;
        }

        return RepositoryFactory::getUserRepository()->verifyUserPassword($domainName, $uid, $password);
    }

    /**
     * @return array{uid: string, domain: string, email: string} the account of the session
     */
    public static function account(): array
    {
        $email = (string) ($_SESSION['email'] ?? '');
        [$uid, $domain] = explode('@', $email, 2) + ['', ''];

        return ['uid' => $uid, 'domain' => $domain, 'email' => $email];
    }

    public static function user(): ?User
    {
        $account = self::account();

        return RepositoryFactory::getUserRepository()->getUser($account['domain'], $account['uid']);
    }

    public static function domainSettings(): DomainSettings
    {
        $domain = RepositoryFactory::getDomainRepository()->getDomain(self::account()['domain']);

        return DomainSettings::fromSettingsString($domain->settings ?? '');
    }

    /**
     * Returns the pages that the user may open, in menu order. The domain admin closes
     * preferences, and the Amavisd pages need the Amavisd integration.
     *
     * @return list<string>
     */
    public static function openPages(DomainSettings $settings, bool $amavisdEnabled): array
    {
        $open = [];
        foreach (self::PAGES as $page => $preference) {
            $needsAmavisd = in_array($page, self::AMAVISD_PAGES, true);
            if (ProfileToggles::preferenceOpen($settings, $preference) && ($amavisdEnabled || !$needsAmavisd)) {
                $open[] = $page;
            }
        }

        return $open;
    }
}
