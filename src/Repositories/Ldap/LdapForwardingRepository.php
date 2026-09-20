<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Models\LdapConnection;
use App\Models\Settings;
use App\Repositories\ForwardingRepositoryInterface;
use App\Utils\LdapUtils;

class LdapForwardingRepository implements ForwardingRepositoryInterface
{
    public function getForwardings(string $email): array
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getEmailDn($email);

        $result = @ldap_read($conn, $dn, '(objectClass=mailUser)', ['mailForwardingAddress']);

        if ($result === false || ldap_count_entries($conn, $result) === 0) {
            return [];
        }

        $entries = ldap_get_entries($conn, $result);
        $forwardings = [];

        if (isset($entries[0]['mailforwardingaddress'])) {
            for ($i = 0; $i < ($entries[0]['mailforwardingaddress']['count'] ?? 0); $i++) {
                $addr = $entries[0]['mailforwardingaddress'][$i];
                if ($addr !== $email) {
                    $forwardings[] = $addr;
                }
            }
        }

        sort($forwardings);
        return $forwardings;
    }

    public function setForwardings(string $email, string $domain, array $forwardingAddresses): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getEmailDn($email);

        // Build full list: keep self-forwarding if it exists, add new addresses
        $keepCopy = $this->getKeepCopy($email);
        $allAddresses = [];

        if ($keepCopy) {
            $allAddresses[] = $email;
        }

        foreach ($forwardingAddresses as $addr) {
            $addr = trim($addr);
            if ($addr !== '' && $addr !== $email) {
                $allAddresses[] = $addr;
            }
        }

        if ($allAddresses !== []) {
            if (!@ldap_mod_replace($conn, $dn, ['mailForwardingAddress' => $allAddresses])) {
                throw new \RuntimeException('LDAP forwarding update failed: ' . ldap_error($conn));
            }
        } else {
            // Remove all forwarding addresses
            @ldap_mod_del($conn, $dn, ['mailForwardingAddress' => []]);
        }
    }

    public function getKeepCopy(string $email): bool
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getEmailDn($email);

        $result = @ldap_read($conn, $dn, '(objectClass=mailUser)', ['mailForwardingAddress']);

        if ($result === false || ldap_count_entries($conn, $result) === 0) {
            return true; // Default: keep copy
        }

        $entries = ldap_get_entries($conn, $result);

        if (isset($entries[0]['mailforwardingaddress'])) {
            for ($i = 0; $i < ($entries[0]['mailforwardingaddress']['count'] ?? 0); $i++) {
                if ($entries[0]['mailforwardingaddress'][$i] === $email) {
                    return true;
                }
            }
        }

        // No forwarding addresses at all means keep copy by default
        return !isset($entries[0]['mailforwardingaddress']) || $entries[0]['mailforwardingaddress']['count'] === 0;
    }

    public function setKeepCopy(string $email, string $domain, bool $keepCopy): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $dn = LdapUtils::getEmailDn($email);

        if ($keepCopy) {
            @ldap_mod_add($conn, $dn, ['mailForwardingAddress' => $email]);
        } else {
            @ldap_mod_del($conn, $dn, ['mailForwardingAddress' => [$email]]);
        }
    }
}
