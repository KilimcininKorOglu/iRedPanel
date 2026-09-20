<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Models\Admin;
use App\Models\LdapConnection;
use App\Models\Settings;
use App\Repositories\RepositoryFactory;
use App\Repositories\SearchRepositoryInterface;
use App\Utils\LdapUtils;

/**
 * Searches the LDAP entries in the formats the repositories write: `mailUser` mailboxes
 * (catch-all excluded), `mailAlias` aliases, `mailList` entries with the mlmmj service,
 * and the admins of LdapAdminRepository. Each type returns at most 50 results.
 * A mailbox also matches its per-account alias addresses, which LDAP holds in
 * `shadowAddress`, so the search finds the mailbox behind such an address.
 */
class LdapSearchRepository implements SearchRepositoryInterface
{
    private const LIMIT = 50;

    /** Account type => [result key, object filter, searched attributes]. */
    private const TYPES = [
        'domain' => ['domains', '(objectClass=mailDomain)', ['domainName', 'description']],
        'user' => ['users', '(&(objectClass=mailUser)(!(mail=@*)))', ['mail', 'cn', 'shadowAddress']],
        'alias' => ['aliases', '(objectClass=mailAlias)', ['mail', 'cn']],
        'ml' => ['mailingLists', '(&(objectClass=mailList)(enabledService=mlmmj))', ['mail', 'cn']],
    ];

    public function search(string $query, array $accountTypes = [], array $statusFilter = [], array $managedDomains = []): array
    {
        $results = ['domains' => [], 'users' => [], 'aliases' => [], 'mailingLists' => [], 'admins' => []];
        foreach (self::TYPES as $type => [$key, $objectFilter, $searchAttrs]) {
            if ($accountTypes === [] || in_array($type, $accountTypes, true)) {
                $results[$key] = self::searchType($query, $objectFilter, $searchAttrs, $statusFilter, $managedDomains);
            }
        }

        if (($accountTypes === [] || in_array('admin', $accountTypes, true)) && $managedDomains === []) {
            $results['admins'] = self::searchAdmins($query, $statusFilter);
        }

        return $results;
    }

    private static function searchType(string $query, string $objectFilter, array $searchAttrs, array $statusFilter, array $managedDomains): array
    {
        $safe = ldap_escape($query, '', LDAP_ESCAPE_FILTER);
        $match = implode('', array_map(static fn (string $attr): string => "({$attr}=*{$safe}*)", $searchAttrs));
        $filter = "(&{$objectFilter}(|{$match})" . self::statusClause($statusFilter) . ')';

        $items = array_map(
            self::toItem(...),
            LdapUtils::searchEntries(
                LdapConnection::getInstance()->getConn(),
                'o=domains,' . Settings::getInstance()->ldapRootDn,
                $filter,
                ['mail', 'domainName', 'cn', 'description', 'accountStatus']
            )
        );
        if ($managedDomains !== []) {
            $items = array_filter($items, static fn (array $item): bool => in_array($item['domain'], $managedDomains, true));
        }
        usort($items, static fn (array $a, array $b): int => strcmp($a['username'], $b['username']));

        return array_slice($items, 0, self::LIMIT);
    }

    private static function searchAdmins(string $query, array $statusFilter): array
    {
        $wanted = self::wantedStatus($statusFilter);
        $admins = array_filter(
            RepositoryFactory::getAdminRepository()->getAdmins(),
            static fn (Admin $admin): bool => (stripos($admin->username, $query) !== false || stripos($admin->name, $query) !== false)
                && ($wanted === null || $admin->active === $wanted)
        );

        return array_slice(array_map(
            static fn (Admin $admin): array => ['username' => $admin->username, 'name' => $admin->name, 'active' => $admin->active ? 1 : 0],
            array_values($admins)
        ), 0, self::LIMIT);
    }

    /**
     * @return bool|null true for active only, false for disabled only, null for both
     */
    private static function wantedStatus(array $statusFilter): ?bool
    {
        $active = in_array('active', $statusFilter, true);
        $disabled = in_array('disabled', $statusFilter, true);

        return $active === $disabled ? null : $active;
    }

    private static function statusClause(array $statusFilter): string
    {
        return match (self::wantedStatus($statusFilter)) {
            true => '(accountStatus=active)',
            false => '(!(accountStatus=active))',
            null => '',
        };
    }

    private static function toItem(array $entry): array
    {
        $address = LdapUtils::allValues($entry, 'mail')[0] ?? LdapUtils::allValues($entry, 'domainName')[0] ?? '';

        return [
            'domain' => str_contains($address, '@') ? explode('@', $address, 2)[1] : $address,
            'username' => $address,
            'address' => $address,
            'name' => LdapUtils::allValues($entry, 'cn')[0] ?? '',
            'description' => LdapUtils::allValues($entry, 'description')[0] ?? '',
            'active' => (LdapUtils::allValues($entry, 'accountStatus')[0] ?? 'active') === 'active' ? 1 : 0,
        ];
    }
}
