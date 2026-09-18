<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Models\LdapConnection;
use App\Models\MailingList;
use App\Models\PaginatedResult;
use App\Models\Settings;
use App\Repositories\MailingListRepositoryInterface;
use App\Utils\LdapUtils;

class LdapMailingListRepository implements MailingListRepositoryInterface
{
    public function getMailingListsPaginated(int $page, int $perPage, ?string $domain = null): PaginatedResult
    {
        $conn = LdapConnection::getInstance()->getConn();
        $settings = Settings::getInstance();

        if ($domain !== null) {
            $baseDn = "ou=Groups,domainName=" . ldap_escape($domain, '', LDAP_ESCAPE_DN) . ",o=domains,{$settings->ldapRootDn}";
        } else {
            $baseDn = "o=domains,{$settings->ldapRootDn}";
        }

        $filter = '(&(objectClass=mailList)(enabledService=mlmmj))';
        $attrs = ['mail', 'cn', 'accountStatus', 'accessPolicy', 'transport', 'maxMessageSize', 'maxMembers'];

        $result = @ldap_search($conn, $baseDn, $filter, $attrs);
        if ($result === false) {
            return new PaginatedResult([], 0, $page, $perPage);
        }

        $entries = ldap_get_entries($conn, $result);
        $totalCount = $entries['count'] ?? 0;

        $items = [];
        for ($i = 0; $i < $totalCount; $i++) {
            $items[] = $this->entryToMailingList($entries[$i]);
        }

        usort($items, fn(MailingList $a, MailingList $b) => strcmp($a->address, $b->address));

        $offset = ($page - 1) * $perPage;
        $pageItems = array_slice($items, $offset, $perPage);

        return new PaginatedResult($pageItems, $totalCount, $page, $perPage);
    }

    public function getMailingList(string $address): ?MailingList
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = $this->getMailingListDn($address);

        $attrs = ['mail', 'cn', 'accountStatus', 'accessPolicy', 'transport', 'maxMessageSize', 'maxMembers'];
        $result = @ldap_read($conn, $dn, '(&(objectClass=mailList)(enabledService=mlmmj))', $attrs);
        if ($result === false) {
            return null;
        }

        $entries = ldap_get_entries($conn, $result);
        if (($entries['count'] ?? 0) === 0) {
            return null;
        }

        return $this->entryToMailingList($entries[0]);
    }

    public function createMailingList(string $address, string $domain, string $name,
                                     string $accessPolicy, int $maxMsgSize): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = $this->getMailingListDn($address);

        $entry = [
            'objectClass' => ['mailList'],
            'mail' => $address,
            'accountStatus' => 'active',
            'accessPolicy' => $accessPolicy,
            'enabledService' => ['mlmmj'],
            'transport' => "mlmmj:{$address}",
        ];

        if ($name !== '') {
            $entry['cn'] = $name;
        }
        if ($maxMsgSize > 0) {
            $entry['maxMessageSize'] = (string) $maxMsgSize;
        }

        return @ldap_add($conn, $dn, $entry);
    }

    public function updateMailingList(string $address, string $name, string $accessPolicy,
                                     int $maxMsgSize, bool $active): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = $this->getMailingListDn($address);

        $modifications = [
            LdapUtils::modReplace('accessPolicy', $accessPolicy),
            LdapUtils::modReplace('accountStatus', $active ? 'active' : 'disabled'),
            LdapUtils::modReplace('cn', $name !== '' ? $name : null),
            LdapUtils::modReplace('maxMessageSize', $maxMsgSize > 0 ? (string) $maxMsgSize : null),
        ];

        return @ldap_modify_batch($conn, $dn, $modifications);
    }

    public function deleteMailingList(string $address): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = $this->getMailingListDn($address);

        return @ldap_delete($conn, $dn);
    }

    public function getOwners(string $address): array
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = $this->getMailingListDn($address);

        $result = @ldap_read($conn, $dn, '(objectClass=*)', ['listOwner']);
        if ($result === false) {
            return [];
        }

        $entries = ldap_get_entries($conn, $result);
        if (($entries['count'] ?? 0) === 0) {
            return [];
        }

        $owners = [];
        $count = $entries[0]['listowner']['count'] ?? 0;
        for ($i = 0; $i < $count; $i++) {
            $owners[] = $entries[0]['listowner'][$i];
        }

        sort($owners);
        return $owners;
    }

    public function setOwners(string $address, array $owners): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = $this->getMailingListDn($address);

        $owners = array_filter(array_map('trim', $owners));

        if (!empty($owners)) {
            $modification = [
                'attrib' => 'listOwner',
                'modtype' => LDAP_MODIFY_BATCH_REPLACE,
                'values' => array_values($owners),
            ];
        } else {
            $modification = [
                'attrib' => 'listOwner',
                'modtype' => LDAP_MODIFY_BATCH_REMOVE_ALL,
            ];
        }

        return @ldap_modify_batch($conn, $dn, [$modification]);
    }

    public function enableDisableMailingList(string $address, bool $active): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = $this->getMailingListDn($address);

        $modification = LdapUtils::modReplace('accountStatus', $active ? 'active' : 'disabled');
        return @ldap_modify_batch($conn, $dn, [$modification]);
    }

    private function getMailingListDn(string $address): string
    {
        $settings = Settings::getInstance();
        $domain = explode('@', $address, 2)[1] ?? '';
        $safeDomain = ldap_escape($domain, '', LDAP_ESCAPE_DN);
        $safeAddress = ldap_escape($address, '', LDAP_ESCAPE_DN);

        return "mail={$safeAddress},ou=Groups,domainName={$safeDomain},o=domains,{$settings->ldapRootDn}";
    }

    private function entryToMailingList(array $entry): MailingList
    {
        $email = $entry['mail'][0] ?? '';
        $domain = str_contains($email, '@') ? explode('@', $email, 2)[1] : '';

        return new MailingList(
            address: $email,
            domain: $domain,
            name: $entry['cn'][0] ?? '',
            accessPolicy: $entry['accesspolicy'][0] ?? 'public',
            transport: $entry['transport'][0] ?? '',
            maxMsgSize: (int) ($entry['maxmessagesize'][0] ?? 0),
            active: ($entry['accountstatus'][0] ?? 'active') === 'active',
        );
    }
}
