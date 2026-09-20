<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Models\LdapConnection;
use App\Models\MailingList;
use App\Models\PaginatedResult;
use App\Models\Settings;
use App\Repositories\MailingListRepositoryInterface;
use App\Utils\LdapUtils;

/**
 * mlmmj mailing lists in the layout of mlmmjadmin's iRedMail LDAP backend: a `mailList`
 * entry `mail=<address>,ou=Groups` under the domain, with `enabledService: mail, deliver,
 * mlmmj`, `mtaTransport: mlmmj:<domain>/<list>` and a `mailingListID`. Owners are stored in
 * `listOwner` and in `listAllowedUser`, which iRedAPD reads as senders that bypass the
 * access policy.
 */
class LdapMailingListRepository implements MailingListRepositoryInterface
{
    private const ATTRS = ['mail', 'cn', 'accountStatus', 'accessPolicy', 'mtaTransport', 'maxMessageSize', 'mailingListID'];

    private const LIST_FILTER = '(&(objectClass=mailList)(enabledService=mlmmj))';

    public function getMailingListsPaginated(int $page, int $perPage, ?string $domain = null, ?bool $activeOnly = null): PaginatedResult
    {
        $baseDn = $domain !== null
            ? 'ou=Groups,' . LdapUtils::getDomainDn($domain)
            : 'o=domains,' . Settings::getInstance()->ldapRootDn;

        $items = array_map(
            self::toMailingList(...),
            LdapUtils::searchEntries(self::conn(), $baseDn, '(&' . self::LIST_FILTER . LdapUtils::statusFilter($activeOnly) . ')', self::ATTRS)
        );
        usort($items, static fn (MailingList $a, MailingList $b): int => strcmp($a->address, $b->address));

        return new PaginatedResult(array_slice($items, ($page - 1) * $perPage, $perPage), count($items), $page, $perPage);
    }

    public function getMailingList(string $address): ?MailingList
    {
        $entry = LdapUtils::readEntry(self::conn(), self::listDn($address), self::LIST_FILTER, self::ATTRS);

        return $entry === null ? null : self::toMailingList($entry);
    }

    public function getMailingListById(string $mlid): ?MailingList
    {
        $filter = '(&' . self::LIST_FILTER . '(mailingListID=' . ldap_escape($mlid, '', LDAP_ESCAPE_FILTER) . '))';
        $entries = LdapUtils::searchEntries(self::conn(), 'o=domains,' . Settings::getInstance()->ldapRootDn, $filter, self::ATTRS);

        return $entries === [] ? null : self::toMailingList($entries[0]);
    }

    /**
     * The iRedMail LDAP schema has no newsletter attribute.
     */
    public function supportsNewsletter(): bool
    {
        return false;
    }

    public function setNewsletter(string $address, bool $enabled): void
    {
        throw new \LogicException('The LDAP backend does not store the newsletter flag');
    }

    public function createMailingList(string $address, string $domain, string $name,
                                     string $accessPolicy, int $maxMsgSize): bool
    {
        $conn = self::conn();
        $entry = [
            'objectClass' => ['mailList'],
            'mail' => strtolower($address),
            'accountStatus' => 'active',
            'enabledService' => ['mail', 'deliver', 'mlmmj'],
            'mtaTransport' => MailingList::transportFor($address),
            'mailingListID' => MailingList::generateId(),
            'accessPolicy' => $accessPolicy,
        ];
        if ($name !== '') {
            $entry['cn'] = $name;
        }
        if ($maxMsgSize > 0) {
            $entry['maxMessageSize'] = (string) $maxMsgSize;
        }
        $shadowAddresses = LdapUtils::aliasDomainAddresses($conn, $address);
        if ($shadowAddresses !== []) {
            $entry['shadowAddress'] = $shadowAddresses;
        }

        if (!@ldap_add($conn, self::listDn($address), $entry)) {
            throw new \RuntimeException("LDAP mailing list creation failed for '{$address}': " . ldap_error($conn));
        }

        return true;
    }

    public function updateMailingList(string $address, string $name, string $accessPolicy,
                                     int $maxMsgSize, bool $active): bool
    {
        LdapUtils::replaceValues(self::conn(), self::listDn($address), [
            'accessPolicy' => [$accessPolicy],
            'accountStatus' => [$active ? 'active' : 'disabled'],
            'cn' => $name !== '' ? [$name] : [],
            'maxMessageSize' => $maxMsgSize > 0 ? [(string) $maxMsgSize] : [],
        ]);

        return true;
    }

    public function deleteMailingList(string $address): bool
    {
        $conn = self::conn();
        if (!@ldap_delete($conn, self::listDn($address))) {
            throw new \RuntimeException("LDAP mailing list deletion failed for '{$address}': " . ldap_error($conn));
        }

        return true;
    }

    public function getOwners(string $address): array
    {
        $entry = LdapUtils::readEntry(self::conn(), self::listDn($address), self::LIST_FILTER, ['listOwner']);
        $owners = LdapUtils::allValues($entry ?? [], 'listOwner');
        sort($owners);

        return $owners;
    }

    public function setOwners(string $address, array $owners): bool
    {
        $owners = array_values(array_unique(array_filter(
            array_map(static fn (string $o): string => strtolower(trim($o)), $owners),
            static fn (string $o): bool => $o !== ''
        )));
        LdapUtils::replaceValues(self::conn(), self::listDn($address), ['listOwner' => $owners, 'listAllowedUser' => $owners]);

        return true;
    }

    public function enableDisableMailingList(string $address, bool $active): bool
    {
        LdapUtils::replaceValues(self::conn(), self::listDn($address), ['accountStatus' => [$active ? 'active' : 'disabled']]);

        return true;
    }

    private static function conn(): \LDAP\Connection
    {
        return LdapConnection::getInstance()->getConn();
    }

    private static function listDn(string $address): string
    {
        return LdapUtils::accountDn($address, 'Groups');
    }

    private static function toMailingList(array $entry): MailingList
    {
        $address = LdapUtils::allValues($entry, 'mail')[0] ?? '';

        return new MailingList(
            address: $address,
            domain: explode('@', $address, 2)[1] ?? '',
            name: LdapUtils::allValues($entry, 'cn')[0] ?? '',
            accessPolicy: LdapUtils::allValues($entry, 'accessPolicy')[0] ?? 'public',
            transport: LdapUtils::allValues($entry, 'mtaTransport')[0] ?? '',
            maxMsgSize: (int) (LdapUtils::allValues($entry, 'maxMessageSize')[0] ?? 0),
            active: (LdapUtils::allValues($entry, 'accountStatus')[0] ?? 'active') === 'active',
            mlid: LdapUtils::allValues($entry, 'mailingListID')[0] ?? '',
        );
    }
}
