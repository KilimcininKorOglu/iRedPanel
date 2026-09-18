<?php

declare(strict_types=1);

namespace App\Repositories\Mysql;

use App\Models\PaginatedResult;
use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use App\Utils\PasswordVerifier;

class MysqlUserRepository implements UserRepositoryInterface
{
    public function getUser(string $domain, string $userId): ?User
    {
        $pdo = MysqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "SELECT username, name, first_name, last_name,
                    quota, employeeid, mobile, telephone, active,
                    isglobaladmin, rank, domain,
                    enablesmtp, enablesmtpsecured, enablepop3, enablepop3secured,
                    enableimap, enableimapsecured, enablemanagesieve,
                    enablemanagesievesecured, enablesogo
             FROM mailbox
             WHERE username = :username AND domain = :domain
             LIMIT 1"
        );
        $stmt->execute([
            'username' => "{$userId}@{$domain}",
            'domain' => $domain,
        ]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return self::rowToUser($row);
    }

    public function getUsers(string $domain): array
    {
        $pdo = MysqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "SELECT username, name, first_name, last_name,
                    quota, employeeid, mobile, telephone, active,
                    isglobaladmin, rank, domain,
                    enablesmtp, enablesmtpsecured, enablepop3, enablepop3secured,
                    enableimap, enableimapsecured, enablemanagesieve,
                    enablemanagesievesecured, enablesogo
             FROM mailbox
             WHERE domain = :domain
             ORDER BY username"
        );
        $stmt->execute(['domain' => $domain]);

        $users = [];
        while ($row = $stmt->fetch()) {
            $users[] = self::rowToUser($row);
        }

        return $users;
    }

    public function updateUser(string $domain, User $user): void
    {
        $pdo = MysqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "UPDATE mailbox SET
                name = :cn,
                first_name = :givenName,
                last_name = :sn,
                quota = :quota,
                employeeid = :employeeNumber,
                rank = :title,
                mobile = :mobile,
                telephone = :telephoneNumber,
                active = :active,
                isglobaladmin = :isGlobalAdmin,
                enablesmtp = :enableSmtp,
                enablesmtpsecured = :enableSmtpSecured,
                enablepop3 = :enablePop3,
                enablepop3secured = :enablePop3Secured,
                enableimap = :enableImap,
                enableimapsecured = :enableImapSecured,
                enablemanagesieve = :enableManagesieve,
                enablemanagesievesecured = :enableManagesieveSecured,
                enablesogo = :enableSogo
             WHERE username = :username AND domain = :domain"
        );
        $stmt->execute([
            'cn' => $user->cn,
            'givenName' => $user->givenName,
            'sn' => $user->sn,
            'quota' => $user->mailQuota,
            'employeeNumber' => $user->employeeNumber,
            'title' => $user->title,
            'mobile' => $user->mobile,
            'telephoneNumber' => $user->telephoneNumber,
            'active' => $user->accountStatus ? 1 : 0,
            'isGlobalAdmin' => $user->domainGlobalAdmin ? 1 : 0,
            'enableSmtp' => $user->enableSmtp ? 1 : 0,
            'enableSmtpSecured' => $user->enableSmtpSecured ? 1 : 0,
            'enablePop3' => $user->enablePop3 ? 1 : 0,
            'enablePop3Secured' => $user->enablePop3Secured ? 1 : 0,
            'enableImap' => $user->enableImap ? 1 : 0,
            'enableImapSecured' => $user->enableImapSecured ? 1 : 0,
            'enableManagesieve' => $user->enableManagesieve ? 1 : 0,
            'enableManagesieveSecured' => $user->enableManagesieveSecured ? 1 : 0,
            'enableSogo' => $user->enableSogo ? 1 : 0,
            'username' => "{$user->uid}@{$domain}",
            'domain' => $domain,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException("User '{$user->uid}@{$domain}' not found or no changes applied");
        }
    }

    public function updateUserPassword(string $domain, string $userUid, string $passwordHash): void
    {
        $pdo = MysqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "UPDATE mailbox SET password = :password WHERE username = :username AND domain = :domain"
        );
        $stmt->execute([
            'password' => $passwordHash,
            'username' => "{$userUid}@{$domain}",
            'domain' => $domain,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException("User '{$userUid}@{$domain}' not found");
        }
    }

    public function createUser(string $domain, User $user, string $passwordHash): void
    {
        $pdo = MysqlConnection::getInstance()->getPdo();
        $settings = \App\Models\Settings::getInstance();
        $username = "{$user->uid}@{$domain}";

        $pdo->beginTransaction();
        try {
            // Lock domain row to prevent TOCTOU race on mailbox count/quota
            $lockStmt = $pdo->prepare(
                "SELECT mailboxes, maxquota, quota,
                        (SELECT COUNT(*) FROM mailbox WHERE domain = :d1) AS userCount,
                        (SELECT COALESCE(SUM(quota), 0) FROM mailbox WHERE domain = :d2) AS quotaUsed
                 FROM domain WHERE domain = :d3 FOR UPDATE"
            );
            $lockStmt->execute(['d1' => $domain, 'd2' => $domain, 'd3' => $domain]);
            $domainRow = $lockStmt->fetch();

            if ($domainRow) {
                $mailboxes = (int) $domainRow['mailboxes'];
                $maxQuota = (int) $domainRow['maxquota'];
                $totalQuota = (int) $domainRow['quota'];
                $userCount = (int) $domainRow['userCount'];
                $quotaUsed = (int) $domainRow['quotaUsed'];

                if ($mailboxes > 0 && $userCount >= $mailboxes) {
                    $pdo->rollBack();
                    throw new \RuntimeException("Domain mailbox limit reached ({$userCount}/{$mailboxes})");
                }
                if ($maxQuota > 0 && $user->mailQuota > $maxQuota) {
                    $pdo->rollBack();
                    throw new \RuntimeException("User quota exceeds domain maximum ({$maxQuota} MB)");
                }
                if ($totalQuota > 0 && ($quotaUsed + $user->mailQuota) > $totalQuota) {
                    $pdo->rollBack();
                    throw new \RuntimeException("Total domain quota would be exceeded");
                }
            } else {
                $pdo->rollBack();
                throw new \RuntimeException("Domain '{$domain}' not found");
            }

            $stmt = $pdo->prepare(
            "INSERT INTO mailbox
                (username, password, name, first_name, last_name,
                 quota, employeeid, rank, mobile, telephone,
                 domain, active, isglobaladmin, storagebasedirectory,
                 storagenode, maildir)
             VALUES
                (:username, :password, :cn, :givenName, :sn,
                 :quota, :employeeNumber, :title, :mobile, :telephoneNumber,
                 :domain, :active, :isGlobalAdmin, :storageBase,
                 :storageNode, :maildir)"
        );
        $stmt->execute([
            'username' => $username,
            'password' => $passwordHash,
            'cn' => $user->cn,
            'givenName' => $user->givenName,
            'sn' => $user->sn,
            'quota' => $user->mailQuota,
            'employeeNumber' => $user->employeeNumber,
            'title' => $user->title,
            'mobile' => $user->mobile,
            'telephoneNumber' => $user->telephoneNumber,
            'domain' => $domain,
            'active' => $user->accountStatus ? 1 : 0,
            'isGlobalAdmin' => $user->domainGlobalAdmin ? 1 : 0,
            'storageBase' => $settings->vmailPath,
            'storageNode' => $settings->storageNode,
            'maildir' => "{$domain}/{$user->uid}/",
        ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function supportsCreateUser(): bool
    {
        return true;
    }

    public function getUsersPaginated(string $domain, int $page, int $perPage, ?string $startsWith = null, ?bool $activeOnly = null, string $sortBy = 'uid', string $sortDir = 'asc'): PaginatedResult
    {
        $pdo = MysqlConnection::getInstance()->getPdo();
        $offset = ($page - 1) * $perPage;

        $where = "domain = :domain";
        $params = ['domain' => $domain];

        if ($startsWith !== null && $startsWith !== '') {
            $where .= " AND username LIKE :startsWith";
            $params['startsWith'] = strtolower($startsWith) . "%@{$domain}";
        }

        if ($activeOnly === true) {
            $where .= " AND active = 1";
        } elseif ($activeOnly === false) {
            $where .= " AND active = 0";
        }

        // Count
        $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM mailbox WHERE {$where}");
        $countStmt->execute($params);
        $totalCount = (int) $countStmt->fetch()['total'];

        // Validate sort column
        $allowedSorts = ['uid' => 'username', 'mailQuota' => 'quota', 'accountStatus' => 'active', 'cn' => 'name'];
        $orderColumn = $allowedSorts[$sortBy] ?? 'username';
        $orderDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        $stmt = $pdo->prepare(
            "SELECT username, name, first_name, last_name,
                    quota, employeeid, mobile, telephone, active,
                    isglobaladmin, rank, domain,
                    enablesmtp, enablesmtpsecured, enablepop3, enablepop3secured,
                    enableimap, enableimapsecured, enablemanagesieve,
                    enablemanagesievesecured, enablesogo
             FROM mailbox
             WHERE {$where}
             ORDER BY {$orderColumn} {$orderDir}
             LIMIT :perPage OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('perPage', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        while ($row = $stmt->fetch()) {
            $items[] = self::rowToUser($row);
        }

        return new PaginatedResult($items, $totalCount, $page, $perPage);
    }

    public function deleteUser(string $domain, string $userUid, string $adminEmail): void
    {
        $pdo = MysqlConnection::getInstance()->getPdo();
        $username = "{$userUid}@{$domain}";

        $pdo->beginTransaction();
        try {
            // Record mailbox for deferred deletion
            $stmt = $pdo->prepare(
                "INSERT INTO deleted_mailboxes (username, maildir, domain, admin)
                 SELECT username,
                        CONCAT(storagebasedirectory, '/', storagenode, '/', maildir),
                        domain, :admin
                 FROM mailbox
                 WHERE username = :username AND domain = :domain"
            );
            $stmt->execute(['admin' => $adminEmail, 'username' => $username, 'domain' => $domain]);

            // Delete from related tables
            $stmt = $pdo->prepare("DELETE FROM forwardings WHERE address = :u OR forwarding = :u2");
            $stmt->execute(['u' => $username, 'u2' => $username]);

            $stmt = $pdo->prepare("DELETE FROM used_quota WHERE username = :username");
            $stmt->execute(['username' => $username]);

            $stmt = $pdo->prepare("DELETE FROM domain_admins WHERE username = :username");
            $stmt->execute(['username' => $username]);

            $stmt = $pdo->prepare("DELETE FROM mailbox WHERE username = :username AND domain = :domain");
            $stmt->execute(['username' => $username, 'domain' => $domain]);

            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw new \RuntimeException("Failed to delete user '{$username}': " . $e->getMessage());
        }
    }

    public function verifyUserPassword(string $domain, string $userUid, string $password): bool
    {
        $pdo = MysqlConnection::getInstance()->getPdo();
        $username = "{$userUid}@{$domain}";

        $stmt = $pdo->prepare("SELECT password FROM mailbox WHERE username = :username AND domain = :domain LIMIT 1");
        $stmt->execute(['username' => $username, 'domain' => $domain]);
        $row = $stmt->fetch();

        if ($row === false) {
            return false;
        }

        return PasswordVerifier::verify($password, $row['password']);
    }

    public function renameUser(string $domain, string $oldUid, string $newUid): void
    {
        $pdo = MysqlConnection::getInstance()->getPdo();
        $oldEmail = "{$oldUid}@{$domain}";
        $newEmail = "{$newUid}@{$domain}";

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE mailbox SET username = :new WHERE username = :old AND domain = :domain")
                ->execute(['new' => $newEmail, 'old' => $oldEmail, 'domain' => $domain]);

            $tables = [
                ['forwardings', 'address'],
                ['forwardings', 'forwarding'],
                ['moderators', 'address'],
                ['moderators', 'moderator'],
                ['sender_bcc_user', 'username'],
                ['recipient_bcc_user', 'username'],
                ['sender_relayhost', 'account'],
                ['domain_admins', 'username'],
            ];

            foreach ($tables as [$table, $column]) {
                try {
                    $pdo->prepare("UPDATE {$table} SET {$column} = :new WHERE {$column} = :old")
                        ->execute(['new' => $newEmail, 'old' => $oldEmail]);
                } catch (\PDOException $e) {
                    // Table may not exist — skip
                }
            }

            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Converts a MySQL row to a User model.
     * Maps: name→cn, first_name→givenName, last_name→sn,
     *       employeeid→employeeNumber, rank→title, telephone→telephoneNumber
     */
    private static function rowToUser(array $row): User
    {
        $username = $row['username'] ?? '';
        $uid = str_contains($username, '@') ? explode('@', $username)[0] : $username;

        return new User(
            uid: $uid,
            accountStatus: (bool) ($row['active'] ?? 0),
            mailQuota: (int) ($row['quota'] ?? 0),
            cn: $row['name'] ?? '',
            givenName: $row['first_name'] ?? '',
            sn: $row['last_name'] ?? '',
            employeeNumber: $row['employeeid'] ?? '',
            title: $row['rank'] ?? '',
            mobile: $row['mobile'] ?? '',
            telephoneNumber: $row['telephone'] ?? '',
            domainGlobalAdmin: (bool) ($row['isglobaladmin'] ?? 0),
            enableSmtp: (bool) ($row['enablesmtp'] ?? 1),
            enableSmtpSecured: (bool) ($row['enablesmtpsecured'] ?? 1),
            enablePop3: (bool) ($row['enablepop3'] ?? 1),
            enablePop3Secured: (bool) ($row['enablepop3secured'] ?? 1),
            enableImap: (bool) ($row['enableimap'] ?? 1),
            enableImapSecured: (bool) ($row['enableimapsecured'] ?? 1),
            enableManagesieve: (bool) ($row['enablemanagesieve'] ?? 1),
            enableManagesieveSecured: (bool) ($row['enablemanagesievesecured'] ?? 1),
            enableSogo: (bool) ($row['enablesogo'] ?? 1),
        );
    }
}
