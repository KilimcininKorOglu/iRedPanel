<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Models\LdapConnection;
use App\Repositories\AuthRepositoryInterface;
use App\Utils\LdapUtils;

class LdapAuthRepository implements AuthRepositoryInterface
{
    /**
     * A standalone or mailbox admin logs in with its own password; the admin must be
     * active and a global admin or the admin of at least one domain.
     */
    public function authenticate(string $email, string $password): bool
    {
        $entry = LdapAdminRepository::findAdminEntry(LdapConnection::getInstance()->getConn(), $email)
            ?? throw new \Exception("User {$email} is not an administrator!");
        if ((LdapUtils::allValues($entry, 'accountStatus')[0] ?? '') !== 'active') {
            throw new \Exception("Administrator {$email} is disabled");
        }
        LdapConnection::verifyPassword($entry['dn'], $password);

        return true;
    }

    public function isGlobalAdmin(string $email): bool
    {
        return (new LdapAdminRepository())->getAdmin($email)?->isGlobalAdmin === true;
    }

    public function getManagedDomains(string $email): array
    {
        return (new LdapAdminRepository())->getManagedDomains($email);
    }

    public function getLanguage(string $email): string
    {
        $entry = LdapAdminRepository::findAdminEntry(LdapConnection::getInstance()->getConn(), $email);

        return LdapUtils::allValues($entry ?? [], 'preferredLanguage')[0] ?? '';
    }

    public function setLanguage(string $email, string $locale): void
    {
        $conn = LdapConnection::getInstance()->getConn();
        $entry = LdapAdminRepository::findAdminEntry($conn, $email)
            ?? throw new \RuntimeException("Admin '{$email}' not found");
        LdapUtils::replaceValues($conn, $entry['dn'], ['preferredLanguage' => [$locale]]);
    }

    public function supportsLanguagePersistence(): bool
    {
        return true;
    }
}
