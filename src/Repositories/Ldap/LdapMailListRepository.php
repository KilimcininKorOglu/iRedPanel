<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Models\LdapConnection;
use App\Models\MailList;
use App\Models\PaginatedResult;
use App\Models\Settings;
use App\Repositories\MailListRepositoryInterface;
use App\Utils\LdapUtils;

/**
 * Mail lists in the iRedMail LDAP layout: a `mailList` entry
 * `mail=<address>,ou=Groups` under the domain. A member carries
 * `memberOfGroup: <list address>`: a local member on its own account entry, an
 * outside member on a `mailExternalUser` entry under `ou=Externals`. Postfix
 * virtual_group_maps.cf reads exactly those entries.
 */
class LdapMailListRepository implements MailListRepositoryInterface
{
    private const LIST_ATTRS = [
        'mail', 'cn', 'accountStatus', 'accessPolicy', 'listModerator', 'listAllowedUser', 'maxMessageSize',
    ];

    /**
     * A mailing list carries the same objectClass plus `enabledService: mlmmj`,
     * and mlmmj owns its spool. This repository takes the other entries only.
     */
    private const LIST_FILTER = '(&(objectClass=mailList)(!(enabledService=mlmmj)))';

    public function isAvailable(): bool
    {
        return Settings::getInstance()->backend === 'ldap';
    }

    public function getMailListsPaginated(?array $domains, int $page, int $perPage, ?bool $activeOnly = null): PaginatedResult
    {
        $items = [];
        foreach ($domains ?? [null] as $domain) {
            $baseDn = $domain === null
                ? 'o=domains,' . Settings::getInstance()->ldapRootDn
                : 'ou=Groups,' . LdapUtils::getDomainDn($domain);
            foreach (self::search($baseDn, '(&' . self::LIST_FILTER . LdapUtils::statusFilter($activeOnly) . ')', self::LIST_ATTRS) as $entry) {
                $items[] = self::toMailList($entry);
            }
        }
        usort($items, static fn (MailList $a, MailList $b): int => strcmp($a->address, $b->address));

        return new PaginatedResult(array_slice($items, ($page - 1) * $perPage, $perPage), count($items), $page, $perPage);
    }

    public function getMailList(string $address): ?MailList
    {
        $entry = LdapUtils::readEntry(
            LdapConnection::getInstance()->getConn(),
            self::listDn($address),
            self::LIST_FILTER,
            self::LIST_ATTRS,
        );
        if ($entry === null) {
            return null;
        }

        $list = self::toMailList($entry);
        $list->members = $this->members($list->address);

        return $list;
    }

    public function createMailList(MailList $list): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $entry = [
            'objectClass' => ['mailList'],
            'mail' => $list->address,
            'accountStatus' => $list->active ? 'active' : 'disabled',
            'enabledService' => ['mail', 'deliver'],
        ] + self::profileValues($list);

        $shadowAddresses = LdapUtils::aliasDomainAddresses($conn, $list->address);
        if ($shadowAddresses !== []) {
            $entry['shadowAddress'] = $shadowAddresses;
        }

        if (!@ldap_add($conn, self::listDn($list->address), array_filter($entry, static fn ($v): bool => $v !== []))) {
            throw new \RuntimeException("LDAP mail list creation failed for '{$list->address}': " . ldap_error($conn));
        }

        $this->setMembers($list->address, $list->members);
    }

    public function updateMailList(MailList $list): void
    {
        LdapUtils::replaceValues(LdapConnection::getInstance()->getConn(), self::listDn($list->address), [
            'accountStatus' => [$list->active ? 'active' : 'disabled'],
        ] + self::profileValues($list));

        $this->setMembers($list->address, $list->members);
    }

    public function deleteMailList(string $address): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $this->setMembers($address, []);
        if (!@ldap_delete($conn, self::listDn($address))) {
            throw new \RuntimeException("LDAP mail list deletion failed for '{$address}': " . ldap_error($conn));
        }
    }

    public function isAddressInUse(string $address): bool
    {
        $escaped = ldap_escape($address, '', LDAP_ESCAPE_FILTER);
        $conn = LdapConnection::getInstance()->getConn();
        $result = @ldap_search($conn, Settings::getInstance()->ldapRootDn, "(|(mail={$escaped})(shadowAddress={$escaped}))", ['mail'], 0, 1);
        if ($result === false) {
            throw new \RuntimeException('LDAP search failed: ' . ldap_error($conn));
        }

        return (ldap_count_entries($conn, $result)) > 0;
    }

    /**
     * The addresses that receive the mail of the list.
     *
     * @return list<string>
     */
    private function members(string $address): array
    {
        $members = array_map(
            static fn (array $entry): string => LdapUtils::allValues($entry, 'mail')[0] ?? '',
            self::memberEntries($address),
        );
        $members = array_values(array_filter($members));
        sort($members);

        return $members;
    }

    /**
     * Writes the member list: a local account gets the `memberOfGroup` value,
     * an outside address its own `mailExternalUser` entry.
     *
     * @param list<string> $members
     */
    private function setMembers(string $address, array $members): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $stored = [];
        foreach (self::memberEntries($address) as $entry) {
            $stored[LdapUtils::allValues($entry, 'mail')[0] ?? ''] = (string) $entry['dn'];
        }

        foreach (array_diff($members, array_keys($stored)) as $member) {
            $this->addMember($conn, $address, $member);
        }
        foreach (array_diff(array_keys($stored), $members) as $member) {
            $this->removeMember($conn, $address, $member, $stored[$member]);
        }
    }

    private function addMember(\LDAP\Connection $conn, string $address, string $member): void
    {
        $dn = self::accountDnOf($member);
        if ($dn !== null) {
            LdapUtils::addValues($conn, $dn, 'memberOfGroup', [$address]);
            return;
        }

        $entry = [
            'objectClass' => ['mailExternalUser'],
            'mail' => $member,
            'accountStatus' => 'active',
            'enabledService' => ['mail', 'deliver'],
            'memberOfGroup' => [$address],
        ];
        if (!@ldap_add($conn, self::externalDn($address, $member), $entry)) {
            throw new \RuntimeException("LDAP member add failed for '{$member}': " . ldap_error($conn));
        }
    }

    /**
     * Removes the member from the list. An outside entry that belongs to no
     * other list goes with it, because nothing else reads it.
     */
    private function removeMember(\LDAP\Connection $conn, string $address, string $member, string $dn): void
    {
        $entry = LdapUtils::readEntry($conn, $dn, '(objectClass=*)', ['objectClass', 'memberOfGroup']);
        $groups = LdapUtils::allValues($entry ?? [], 'memberOfGroup');
        $isExternal = in_array('mailExternalUser', LdapUtils::allValues($entry ?? [], 'objectClass'), true);

        if ($isExternal && count($groups) <= 1) {
            if (!@ldap_delete($conn, $dn)) {
                throw new \RuntimeException("LDAP member removal failed for '{$member}': " . ldap_error($conn));
            }
            return;
        }

        LdapUtils::deleteValues($conn, $dn, 'memberOfGroup', [$address]);
    }

    /**
     * The entries that carry `memberOfGroup` of the list.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function memberEntries(string $address): array
    {
        $escaped = ldap_escape($address, '', LDAP_ESCAPE_FILTER);

        return self::search(
            'o=domains,' . Settings::getInstance()->ldapRootDn,
            "(memberOfGroup={$escaped})",
            ['mail'],
        );
    }

    /**
     * The DN of an account entry that already exists in the directory.
     */
    private static function accountDnOf(string $address): ?string
    {
        $escaped = ldap_escape($address, '', LDAP_ESCAPE_FILTER);
        $entries = self::search('o=domains,' . Settings::getInstance()->ldapRootDn, "(mail={$escaped})", ['mail']);

        return isset($entries[0]['dn']) ? (string) $entries[0]['dn'] : null;
    }

    /**
     * @param string[] $attrs
     * @return array<int, array<string, mixed>>
     */
    private static function search(string $baseDn, string $filter, array $attrs): array
    {
        return LdapUtils::searchEntries(LdapConnection::getInstance()->getConn(), $baseDn, $filter, $attrs);
    }

    /**
     * @return array<string, list<string>> the attributes of the profile fields
     */
    private static function profileValues(MailList $list): array
    {
        return [
            'accessPolicy' => [$list->accessPolicy],
            'cn' => $list->name !== '' ? [$list->name] : [],
            'listModerator' => $list->moderators,
            'listAllowedUser' => $list->allowedSenders,
            'maxMessageSize' => $list->maxMessageSize > 0 ? [(string) $list->maxMessageSize] : [],
        ];
    }

    private static function listDn(string $address): string
    {
        return LdapUtils::accountDn($address, 'Groups');
    }

    /**
     * An outside member lives under the domain of the list it belongs to.
     */
    private static function externalDn(string $listAddress, string $member): string
    {
        $domain = explode('@', strtolower($listAddress), 2)[1] ?? '';

        return 'mail=' . ldap_escape(strtolower($member), '', LDAP_ESCAPE_DN)
            . ',ou=Externals,' . LdapUtils::getDomainDn($domain);
    }

    private static function toMailList(array $entry): MailList
    {
        $address = LdapUtils::allValues($entry, 'mail')[0] ?? '';

        return new MailList(
            address: $address,
            domain: explode('@', $address, 2)[1] ?? '',
            name: LdapUtils::allValues($entry, 'cn')[0] ?? '',
            accessPolicy: LdapUtils::allValues($entry, 'accessPolicy')[0] ?? 'public',
            active: (LdapUtils::allValues($entry, 'accountStatus')[0] ?? 'active') === 'active',
            moderators: LdapUtils::allValues($entry, 'listModerator'),
            allowedSenders: LdapUtils::allValues($entry, 'listAllowedUser'),
            maxMessageSize: (int) (LdapUtils::allValues($entry, 'maxMessageSize')[0] ?? 0),
        );
    }
}
