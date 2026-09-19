<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Models\LdapConnection;
use App\Repositories\RelayRepositoryInterface;
use App\Utils\LdapUtils;

class LdapRelayRepository implements RelayRepositoryInterface
{
    public function getRelayhost(string $account): ?string
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = $this->getDnForAccount($account);

        $result = @ldap_read($conn, $dn, '(objectClass=*)', ['senderRelayHost']);
        if ($result === false) {
            return null;
        }

        $entries = ldap_get_entries($conn, $result);
        if (($entries['count'] ?? 0) === 0) {
            return null;
        }

        return $entries[0]['senderrelayhost'][0] ?? null;
    }

    public function setRelayhost(string $account, ?string $relayhost): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = $this->getDnForAccount($account);

        $modification = LdapUtils::modReplace('senderRelayHost', $relayhost);
        return LdapUtils::modifyBatch($conn, $dn, [$modification]);
    }

    public function getAllRelayhosts(?string $domain = null): array
    {
        // LDAP does not efficiently support listing all relay settings
        return [];
    }

    public function deleteRelayhost(string $account): bool
    {
        return $this->setRelayhost($account, null);
    }

    private function getDnForAccount(string $account): string
    {
        if (str_starts_with($account, '@')) {
            $domain = substr($account, 1);
            return LdapUtils::getDomainDn($domain);
        }

        return LdapUtils::getEmailDn($account);
    }
}
