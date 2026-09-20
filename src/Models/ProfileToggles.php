<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\InvalidInputException;
use App\Utils\NameList;

/**
 * Names of the domain and user profile pages that a global admin can close for the
 * domain admins of a domain, and of the self-service preferences that a domain admin
 * can close for the users of the domain. DomainSettings stores the closed names.
 */
final class ProfileToggles
{
    /** Domain pages that a domain admin edits. The general page (limits) stays global admin only. */
    public const DOMAIN_PROFILES = ['settings', 'catchall', 'bcc', 'relay'];

    /** User pages that a domain admin edits. */
    public const USER_PROFILES = ['general', 'password', 'services', 'forwarding', 'aliases', 'bcc', 'relay', 'disclaimer', 'sharing'];

    /** User page with the shared folders. It needs the database that holds the Dovecot rows. */
    public const SHARING_PAGE = 'sharing';

    /** Self-service pages that a mailbox user edits. */
    public const USER_PREFERENCES = ['personal_info', 'password', 'forwarding', 'wblist', 'spampolicy', 'quarantine', 'received', 'sent'];

    /** Domain service value that allows the users of the domain to log in to self-service. */
    public const SELF_SERVICE = 'self-service';

    /**
     * Domain page with the admins of the domain. It is no toggle: a domain admin always
     * opens it, so a domain admin may promote the mailboxes of its domains.
     */
    public const ADMINS_PAGE = 'admins';

    /**
     * Domain page with the DNS check. It is no toggle either: the page reads DNS only
     * and writes nothing, so a domain admin always opens it for a managed domain.
     */
    public const DNS_PAGE = 'dns';

    /**
     * Returns the domain pages that the admin may open, in tab order.
     *
     * @return list<string>
     */
    public static function openDomainPages(DomainSettings $settings, bool $isGlobalAdmin): array
    {
        if ($isGlobalAdmin) {
            return ['general', ...self::DOMAIN_PROFILES, self::ADMINS_PAGE, self::DNS_PAGE];
        }

        return [...array_diff(self::DOMAIN_PROFILES, $settings->disabledDomainProfiles), self::ADMINS_PAGE, self::DNS_PAGE];
    }

    public static function userPageOpen(DomainSettings $settings, bool $isGlobalAdmin, string $page): bool
    {
        return $isGlobalAdmin || !in_array($page, $settings->disabledUserProfiles, true);
    }

    /**
     * Returns the user pages that the admin may open, in tab order.
     *
     * @return list<string>
     */
    public static function openUserPages(DomainSettings $settings, bool $isGlobalAdmin): array
    {
        return array_values(array_filter(
            self::USER_PROFILES,
            static fn (string $page): bool => self::userPageOpen($settings, $isGlobalAdmin, $page)
        ));
    }

    public static function preferenceOpen(DomainSettings $settings, string $preference): bool
    {
        return $settings->selfService() && !in_array($preference, $settings->disabledUserPreferences, true);
    }

    /**
     * @param string[] $allowed one of the constants above
     * @return list<string>
     * @throws InvalidInputException when $names is not a list of allowed names
     */
    public static function valid(mixed $names, array $allowed, string $field): array
    {
        return NameList::valid($names, $allowed, "{$field} item", 'domain.msg_unknown_profile', 'name');
    }
}
