<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Models\LdapConnection;
use App\Repositories\BccRepositoryInterface;
use App\Utils\LdapUtils;

class LdapBccRepository implements BccRepositoryInterface
{
    public function getDomainSenderBcc(string $domain): ?string
    {
        return $this->getLdapAttribute(LdapUtils::getDomainDn($domain), 'domainSenderBccAddress');
    }

    public function setDomainSenderBcc(string $domain, ?string $bccAddress): bool
    {
        return $this->setDomainBcc($domain, 'domainSenderBccAddress', 'senderbcc', $bccAddress);
    }

    public function getDomainRecipientBcc(string $domain): ?string
    {
        return $this->getLdapAttribute(LdapUtils::getDomainDn($domain), 'domainRecipientBccAddress');
    }

    public function setDomainRecipientBcc(string $domain, ?string $bccAddress): bool
    {
        return $this->setDomainBcc($domain, 'domainRecipientBccAddress', 'recipientbcc', $bccAddress);
    }

    public function getUserSenderBcc(string $email): ?string
    {
        return $this->getLdapAttribute(LdapUtils::getEmailDn($email), 'userSenderBccAddress');
    }

    public function setUserSenderBcc(string $email, ?string $bccAddress): bool
    {
        return $this->setLdapAttribute(LdapUtils::getEmailDn($email), 'userSenderBccAddress', $bccAddress);
    }

    public function getUserRecipientBcc(string $email): ?string
    {
        return $this->getLdapAttribute(LdapUtils::getEmailDn($email), 'userRecipientBccAddress');
    }

    public function setUserRecipientBcc(string $email, ?string $bccAddress): bool
    {
        return $this->setLdapAttribute(LdapUtils::getEmailDn($email), 'userRecipientBccAddress', $bccAddress);
    }

    public function getAllDomainBcc(?string $domain = null): array
    {
        // LDAP does not efficiently support listing all BCC settings across domains
        return [];
    }

    public function getAllUserBcc(?string $domain = null): array
    {
        // LDAP does not efficiently support listing all BCC settings across users
        return [];
    }

    private function getLdapAttribute(string $dn, string $attribute): ?string
    {
        $conn = LdapConnection::getInstance()->getConn();

        $result = @ldap_read($conn, $dn, '(objectClass=*)', [$attribute]);
        if ($result === false) {
            return null;
        }

        $entries = ldap_get_entries($conn, $result);
        if (($entries['count'] ?? 0) === 0) {
            return null;
        }

        $lowerAttr = strtolower($attribute);
        return $entries[0][$lowerAttr][0] ?? null;
    }

    /**
     * Postfix reads a domain BCC address only from a domain with enabledService=<service>,
     * so setting an address also enables the service.
     *
     * @throws \RuntimeException when the directory rejects the change
     */
    private function setDomainBcc(string $domain, string $attribute, string $service, ?string $bccAddress): bool
    {
        $dn = LdapUtils::getDomainDn($domain);
        $this->setLdapAttribute($dn, $attribute, $bccAddress);
        if ($bccAddress === null || $bccAddress === '') {
            return true;
        }

        $conn = LdapConnection::getInstance()->getConn();
        // LDAP error 20 "Type or value exists": the domain already has the service
        if (!@ldap_mod_add($conn, $dn, ['enabledService' => [$service]]) && ldap_errno($conn) !== 20) {
            throw new \RuntimeException("LDAP update of enabledService failed: " . ldap_error($conn));
        }

        return true;
    }

    /**
     * @throws \RuntimeException when the directory rejects the change
     */
    private function setLdapAttribute(string $dn, string $attribute, ?string $value): bool
    {
        $conn = LdapConnection::getInstance()->getConn();

        $modification = LdapUtils::modReplace($attribute, $value);
        if (!LdapUtils::modifyBatch($conn, $dn, [$modification])) {
            throw new \RuntimeException("LDAP update of {$attribute} failed: " . ldap_error($conn));
        }

        return true;
    }
}
