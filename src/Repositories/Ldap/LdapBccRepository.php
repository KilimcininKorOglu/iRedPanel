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
        return $this->getLdapAttribute(LdapUtils::getDomainDn($domain), 'senderBccAddress');
    }

    public function setDomainSenderBcc(string $domain, ?string $bccAddress): bool
    {
        return $this->setLdapAttribute(LdapUtils::getDomainDn($domain), 'senderBccAddress', $bccAddress);
    }

    public function getDomainRecipientBcc(string $domain): ?string
    {
        return $this->getLdapAttribute(LdapUtils::getDomainDn($domain), 'recipientBccAddress');
    }

    public function setDomainRecipientBcc(string $domain, ?string $bccAddress): bool
    {
        return $this->setLdapAttribute(LdapUtils::getDomainDn($domain), 'recipientBccAddress', $bccAddress);
    }

    public function getUserSenderBcc(string $email): ?string
    {
        return $this->getLdapAttribute(LdapUtils::getEmailDn($email), 'senderBccAddress');
    }

    public function setUserSenderBcc(string $email, ?string $bccAddress): bool
    {
        return $this->setLdapAttribute(LdapUtils::getEmailDn($email), 'senderBccAddress', $bccAddress);
    }

    public function getUserRecipientBcc(string $email): ?string
    {
        return $this->getLdapAttribute(LdapUtils::getEmailDn($email), 'recipientBccAddress');
    }

    public function setUserRecipientBcc(string $email, ?string $bccAddress): bool
    {
        return $this->setLdapAttribute(LdapUtils::getEmailDn($email), 'recipientBccAddress', $bccAddress);
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

    private function setLdapAttribute(string $dn, string $attribute, ?string $value): bool
    {
        $conn = LdapConnection::getInstance()->getConn();

        $modification = LdapUtils::modReplace($attribute, $value);
        return LdapUtils::modifyBatch($conn, $dn, [$modification]);
    }
}
