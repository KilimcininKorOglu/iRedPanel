<?php

declare(strict_types=1);

namespace App\Repositories\Pgsql;

use App\Models\Admin;
use App\Repositories\AdminRepositoryInterface;
use App\Repositories\SqlAdminSettings;
use App\Repositories\SqlDomainAdmins;

class PgsqlAdminRepository implements AdminRepositoryInterface
{
    public function getAdmins(): array
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();
        $admins = [];

        // Standalone admins
        $stmt = $pdo->query(
            "SELECT a.username, a.name, a.active, a.created, a.passwordlastchange,
                    CASE WHEN da.domain = 'ALL' THEN 1 ELSE 0 END AS \"isGlobalAdmin\"
             FROM admin a
             LEFT JOIN domain_admins da ON da.username = a.username AND da.domain = 'ALL'
             ORDER BY a.username"
        );
        while ($row = $stmt->fetch()) {
            $admins[] = Admin::fromMysqlRow($row, false);
        }

        // Mailbox-based admins
        $stmt = $pdo->query(
            "SELECT m.username, m.name, m.active, m.created, m.passwordlastchange,
                    m.isglobaladmin AS \"isGlobalAdmin\"
             FROM mailbox m
             WHERE m.isadmin = 1 OR m.isglobaladmin = 1
             ORDER BY m.username"
        );
        while ($row = $stmt->fetch()) {
            // Skip if already listed as standalone admin
            $exists = false;
            foreach ($admins as $existing) {
                if ($existing->username === $row['username']) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $admins[] = Admin::fromMysqlRow($row, true);
            }
        }

        usort($admins, fn(Admin $a, Admin $b) => strcmp($a->username, $b->username));

        return $admins;
    }

    public function getAdmin(string $username): ?Admin
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "SELECT a.username, a.name, a.active, a.created, a.passwordlastchange, a.settings, a.language
             FROM admin a
             WHERE a.username = :username
             LIMIT 1"
        );
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();

        if ($row === false) {
            // Check mailbox-based admins
            $stmt = $pdo->prepare(
                "SELECT m.username, m.name, m.active, m.created, m.passwordlastchange, m.settings, m.language,
                        m.isglobaladmin AS \"isGlobalAdmin\"
                 FROM mailbox m
                 WHERE m.username = :username AND (m.isadmin = 1 OR m.isglobaladmin = 1)
                 LIMIT 1"
            );
            $stmt->execute(['username' => $username]);
            $row = $stmt->fetch();

            if ($row === false) {
                return null;
            }

            return Admin::fromMysqlRow($row, true);
        }

        // Check if global admin
        $daStmt = $pdo->prepare(
            "SELECT 1 FROM domain_admins WHERE username = :username AND domain = 'ALL' LIMIT 1"
        );
        $daStmt->execute(['username' => $username]);
        $row['isGlobalAdmin'] = $daStmt->fetch() !== false ? 1 : 0;

        return Admin::fromMysqlRow($row, false);
    }

    public function createAdmin(Admin $admin, string $passwordHash): void
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO admin (username, password, name, active, settings, language, created)
                 VALUES (:username, :password, :name, :active, :settings, :language, NOW())"
            );
            $stmt->execute([
                'username' => $admin->username,
                'password' => $passwordHash,
                'name' => $admin->name,
                'active' => $admin->active ? 1 : 0,
                'settings' => $admin->mergedSettings(''),
                'language' => $admin->language,
            ]);

            if ($admin->isGlobalAdmin) {
                $stmt = $pdo->prepare(
                    "INSERT INTO domain_admins (username, domain, created, active)
                     VALUES (:username, 'ALL', NOW(), 1)"
                );
                $stmt->execute(['username' => $admin->username]);
            }

            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw new \RuntimeException("Failed to create admin '{$admin->username}': " . $e->getMessage());
        }
    }

    public function updateAdmin(Admin $admin): void
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        if ($admin->isMailboxAdmin) {
            $stmt = $pdo->prepare(
                "UPDATE mailbox SET name = :name, active = :active, isglobaladmin = :isGlobalAdmin, language = :language
                 WHERE username = :username"
            );
            $stmt->execute([
                'name' => $admin->name,
                'active' => $admin->active ? 1 : 0,
                'isGlobalAdmin' => $admin->isGlobalAdmin ? 1 : 0,
                'language' => $admin->language,
                'username' => $admin->username,
            ]);
        } else {
            $stmt = $pdo->prepare(
                "UPDATE admin SET name = :name, active = :active, language = :language WHERE username = :username"
            );
            $stmt->execute([
                'name' => $admin->name,
                'active' => $admin->active ? 1 : 0,
                'language' => $admin->language,
                'username' => $admin->username,
            ]);

            // Update global admin status
            if ($admin->isGlobalAdmin) {
                $stmt = $pdo->prepare(
                    "INSERT INTO domain_admins (username, domain, created, active)
                     VALUES (:username, 'ALL', NOW(), 1)
                     ON CONFLICT (username, domain) DO NOTHING"
                );
                $stmt->execute(['username' => $admin->username]);
            } else {
                $stmt = $pdo->prepare(
                    "DELETE FROM domain_admins WHERE username = :username AND domain = 'ALL'"
                );
                $stmt->execute(['username' => $admin->username]);
            }
        }
    }

    public function updateAdminPassword(string $username, string $passwordHash): void
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        // Try admin table first
        $stmt = $pdo->prepare(
            "UPDATE admin SET password = :password, passwordlastchange = NOW() WHERE username = :username"
        );
        $stmt->execute(['password' => $passwordHash, 'username' => $username]);

        if ($stmt->rowCount() === 0) {
            // Try mailbox table
            $stmt = $pdo->prepare(
                "UPDATE mailbox SET password = :password, passwordlastchange = NOW() WHERE username = :username"
            );
            $stmt->execute(['password' => $passwordHash, 'username' => $username]);

            if ($stmt->rowCount() === 0) {
                throw new \RuntimeException("Admin '{$username}' not found");
            }
        }
    }

    public function deleteAdmin(string $username): void
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $pdo->beginTransaction();
        try {
            // Lock and verify: prevent last global admin deletion (TOCTOU safe).
            // PostgreSQL rejects FOR UPDATE with COUNT(*), so lock the rows and count them here.
            $lockStmt = $pdo->prepare(
                "SELECT username FROM domain_admins WHERE domain = 'ALL' FOR UPDATE"
            );
            $lockStmt->execute();
            $globalCount = count($lockStmt->fetchAll(\PDO::FETCH_COLUMN));

            $isGlobal = $pdo->prepare("SELECT 1 FROM domain_admins WHERE username = :u AND domain = 'ALL'");
            $isGlobal->execute(['u' => $username]);
            if ($isGlobal->fetch() !== false && $globalCount <= 1) {
                throw new \RuntimeException("Cannot delete the last global admin");
            }

            // Delete from admin table
            $stmt = $pdo->prepare("DELETE FROM admin WHERE username = :username");
            $stmt->execute(['username' => $username]);

            // Delete from domain_admins
            $stmt = $pdo->prepare("DELETE FROM domain_admins WHERE username = :username");
            $stmt->execute(['username' => $username]);

            // Revoke admin from mailbox if applicable
            $stmt = $pdo->prepare(
                "UPDATE mailbox SET isadmin = 0, isglobaladmin = 0
                 WHERE username = :username AND (isadmin = 1 OR isglobaladmin = 1)"
            );
            $stmt->execute(['username' => $username]);

            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw new \RuntimeException("Failed to delete admin '{$username}': " . $e->getMessage());
        }
    }

    public function getManagedDomains(string $adminUsername): array
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "SELECT domain FROM domain_admins WHERE username = :username AND domain != 'ALL' ORDER BY domain"
        );
        $stmt->execute(['username' => $adminUsername]);

        $domains = [];
        while ($row = $stmt->fetch()) {
            $domains[] = $row['domain'];
        }

        return $domains;
    }

    public function getDomainAdmins(string $domain): array
    {
        return SqlDomainAdmins::list(PgsqlConnection::getInstance()->getPdo(), $domain);
    }

    public function assignDomainToAdmin(string $adminUsername, string $domain): void
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "INSERT INTO domain_admins (username, domain, created, active)
             VALUES (:username, :domain, NOW(), 1)
             ON CONFLICT (username, domain) DO NOTHING"
        );
        $stmt->execute(['username' => $adminUsername, 'domain' => $domain]);
        SqlDomainAdmins::markMailbox($pdo, $adminUsername);
    }

    public function revokeDomainFromAdmin(string $adminUsername, string $domain): void
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "DELETE FROM domain_admins WHERE username = :username AND domain = :domain"
        );
        $stmt->execute(['username' => $adminUsername, 'domain' => $domain]);
        SqlDomainAdmins::unmarkMailboxWithoutDomains($pdo, $adminUsername);
    }

    public function enableDisableAdmin(string $username, bool $active): void
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        // Try admin table first
        $stmt = $pdo->prepare("UPDATE admin SET active = :active WHERE username = :username");
        $stmt->execute(['active' => $active ? 1 : 0, 'username' => $username]);

        // Also try mailbox table for mailbox-based admins
        $stmt = $pdo->prepare(
            "UPDATE mailbox SET active = :active WHERE username = :username AND (isadmin = 1 OR isglobaladmin = 1)"
        );
        $stmt->execute(['active' => $active ? 1 : 0, 'username' => $username]);
    }

    public function updateAdminSettings(Admin $admin): void
    {
        SqlAdminSettings::write(PgsqlConnection::getInstance()->getPdo(), $admin);
    }

    public function getAdminsPaginated(int $page, int $perPage): \App\Models\PaginatedResult
    {
        // Pages the same list as getAdmins(), which also holds the mailbox-based admins
        $admins = $this->getAdmins();
        $offset = max(0, ($page - 1) * $perPage);

        return new \App\Models\PaginatedResult(array_slice($admins, $offset, $perPage), count($admins), $page, $perPage);
    }

    public function countManagedDomains(string $adminUsername): int
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS total FROM domain_admins WHERE username = :username AND domain != 'ALL'"
        );
        $stmt->execute(['username' => $adminUsername]);

        return (int) $stmt->fetch()['total'];
    }

    public function countGlobalAdmins(): int
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->query(
            "SELECT COUNT(*) AS total FROM domain_admins WHERE domain = 'ALL'"
        );

        return (int) $stmt->fetch()['total'];
    }

    public function getAdminResourceCounts(string $adminUsername): array
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $isGlobal = $pdo->prepare("SELECT 1 FROM domain_admins WHERE username = :u AND domain = 'ALL' LIMIT 1");
        $isGlobal->execute(['u' => $adminUsername]);

        if ($isGlobal->fetch()) {
            $row = $pdo->query(
                "SELECT
                    (SELECT COUNT(*) FROM domain) AS domains,
                    (SELECT COUNT(*) FROM mailbox) AS users,
                    (SELECT COUNT(*) FROM alias) AS aliases,
                    (SELECT COUNT(*) FROM maillists) AS lists,
                    (SELECT COALESCE(SUM(quota), 0) FROM mailbox) AS quotamb"
            )->fetch();
        } else {
            $stmt = $pdo->prepare(
                "SELECT
                    (SELECT COUNT(*) FROM domain_admins WHERE username = :u1 AND domain != 'ALL') AS domains,
                    (SELECT COUNT(*) FROM mailbox WHERE domain IN (SELECT domain FROM domain_admins WHERE username = :u2 AND domain != 'ALL')) AS users,
                    (SELECT COUNT(*) FROM alias WHERE domain IN (SELECT domain FROM domain_admins WHERE username = :u3 AND domain != 'ALL')) AS aliases,
                    (SELECT COUNT(*) FROM maillists WHERE domain IN (SELECT domain FROM domain_admins WHERE username = :u4 AND domain != 'ALL')) AS lists,
                    (SELECT COALESCE(SUM(quota), 0) FROM mailbox WHERE domain IN (SELECT domain FROM domain_admins WHERE username = :u5 AND domain != 'ALL')) AS quotamb"
            );
            $stmt->execute(['u1' => $adminUsername, 'u2' => $adminUsername, 'u3' => $adminUsername, 'u4' => $adminUsername, 'u5' => $adminUsername]);
            $row = $stmt->fetch();
        }

        return [
            'domains' => (int) ($row['domains'] ?? 0),
            'users' => (int) ($row['users'] ?? 0),
            'aliases' => (int) ($row['aliases'] ?? 0),
            'lists' => (int) ($row['lists'] ?? 0),
            'quotaMb' => (int) ($row['quotamb'] ?? 0),
        ];
    }
}
