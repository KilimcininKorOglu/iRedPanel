<?php

declare(strict_types=1);

namespace App\Repositories\Pgsql;

use App\Models\Domain;
use App\Models\PaginatedResult;
use App\Repositories\DomainRepositoryInterface;

class PgsqlDomainRepository implements DomainRepositoryInterface
{
    /** Tables whose `domain` column ties a row to a deleted domain. */
    private const DOMAIN_TABLES_ON_DELETE = [
        'mailbox', 'alias', 'domain_admins', 'forwardings', 'maillists', 'maillist_owners', 'moderators',
        'sender_bcc_domain', 'recipient_bcc_domain', 'sender_bcc_user', 'recipient_bcc_user', 'last_login',
    ];

    public function getDomains(): array
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->query(
            // Quoted aliases keep their case; PostgreSQL folds bare identifiers to lowercase.
            "SELECT d.domain AS \"domainName\",
                    d.active,
                    COUNT(m.username) AS \"userCount\"
             FROM domain d
             LEFT JOIN mailbox m ON m.domain = d.domain
             GROUP BY d.domain, d.active
             ORDER BY d.domain"
        );

        $domains = [];
        while ($row = $stmt->fetch()) {
            $domains[] = [
                'domainName' => $row['domainName'],
                'accountStatus' => $row['active'] ? 'active' : 'disabled',
                'domainCurrentUserNumber' => (string) $row['userCount'],
            ];
        }

        return $domains;
    }

    public function getDomainsPaginated(int $page, int $perPage, ?bool $activeOnly = null): PaginatedResult
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();
        $offset = ($page - 1) * $perPage;

        $where = '1=1';
        if ($activeOnly === true) {
            $where = 'd.active = 1';
        } elseif ($activeOnly === false) {
            $where = 'd.active = 0';
        }

        $countStmt = $pdo->query("SELECT COUNT(*) AS total FROM domain d WHERE {$where}");
        $totalCount = (int) $countStmt->fetch()['total'];

        $stmt = $pdo->prepare(
            "SELECT d.domain, d.description, d.active, d.maxquota, d.quota,
                    d.mailboxes, d.aliases, d.maillists, d.backupmx, d.transport, d.settings, d.created, d.modified,
                    COUNT(m.username) AS \"userCount\",
                    COALESCE(SUM(m.quota), 0) AS \"quotaUsed\"
             FROM domain d
             LEFT JOIN mailbox m ON m.domain = d.domain
             WHERE {$where}
             GROUP BY d.domain, d.description, d.active, d.maxquota, d.quota,
                      d.mailboxes, d.aliases, d.maillists, d.backupmx, d.transport, d.settings, d.created, d.modified
             ORDER BY d.domain
             LIMIT :perPage OFFSET :offset"
        );
        $stmt->bindValue('perPage', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        while ($row = $stmt->fetch()) {
            $items[] = Domain::fromMysqlRow($row);
        }

        return new PaginatedResult($items, $totalCount, $page, $perPage);
    }

    public function getDomain(string $domainName): ?Domain
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "SELECT d.domain, d.description, d.active, d.maxquota, d.quota,
                    d.mailboxes, d.aliases, d.maillists, d.backupmx, d.transport, d.settings, d.disclaimer, d.created, d.modified,
                    COUNT(m.username) AS \"userCount\",
                    COALESCE(SUM(m.quota), 0) AS \"quotaUsed\"
             FROM domain d
             LEFT JOIN mailbox m ON m.domain = d.domain
             WHERE d.domain = :domain
             GROUP BY d.domain, d.description, d.active, d.maxquota, d.quota,
                      d.mailboxes, d.aliases, d.maillists, d.backupmx, d.transport, d.settings, d.disclaimer, d.created, d.modified
             LIMIT 1"
        );
        $stmt->execute(['domain' => $domainName]);

        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return Domain::fromMysqlRow($row);
    }

    public function createDomain(Domain $domain): void
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "INSERT INTO domain (domain, description, active, maxquota, quota, mailboxes, aliases, maillists, backupmx, transport,
                                 settings, disclaimer, created)
             VALUES (:domain, :description, :active, :maxquota, :quota, :mailboxes, :aliases, :maillists, :backupmx, :transport,
                     :settings, :disclaimer, NOW())"
        );
        $stmt->execute([
            'domain' => $domain->domainName,
            'description' => $domain->description,
            'active' => $domain->active ? 1 : 0,
            'maxquota' => $domain->maxQuota,
            'quota' => $domain->quota,
            'mailboxes' => $domain->mailboxes,
            'aliases' => $domain->aliases,
            'maillists' => $domain->lists,
            'backupmx' => $domain->backupMx ? 1 : 0,
            'transport' => $domain->transport,
            'settings' => $domain->settings,
            'disclaimer' => $domain->disclaimer,
        ]);
    }

    public function updateDomain(Domain $domain): void
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "UPDATE domain SET
                description = :description,
                active = :active,
                maxquota = :maxquota,
                quota = :quota,
                mailboxes = :mailboxes,
                aliases = :aliases,
                maillists = :maillists,
                backupmx = :backupmx,
                transport = :transport,
                settings = :settings,
                disclaimer = :disclaimer,
                modified = NOW()
             WHERE domain = :domain"
        );
        $stmt->execute([
            'description' => $domain->description,
            'active' => $domain->active ? 1 : 0,
            'maxquota' => $domain->maxQuota,
            'quota' => $domain->quota,
            'mailboxes' => $domain->mailboxes,
            'aliases' => $domain->aliases,
            'maillists' => $domain->lists,
            'backupmx' => $domain->backupMx ? 1 : 0,
            'transport' => $domain->transport,
            'settings' => $domain->settings,
            'disclaimer' => $domain->disclaimer,
            'domain' => $domain->domainName,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException("Domain '{$domain->domainName}' not found");
        }
    }

    public function deleteDomain(string $domainName, string $adminEmail, ?string $deleteDate = null): void
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $pdo->beginTransaction();
        try {
            // Record mailboxes for deferred deletion
            $stmt = $pdo->prepare(
                "INSERT INTO deleted_mailboxes (username, maildir, domain, admin, delete_date)
                 SELECT username,
                        storagebasedirectory || '/' || storagenode || '/' || maildir,
                        domain, :admin, :deleteDate
                 FROM mailbox
                 WHERE domain = :domain"
            );
            $stmt->execute(['admin' => $adminEmail, 'deleteDate' => $deleteDate, 'domain' => $domainName]);

            // Delete from every table with a domain column
            foreach (self::DOMAIN_TABLES_ON_DELETE as $table) {
                $stmt = $pdo->prepare("DELETE FROM {$table} WHERE domain = :domain");
                $stmt->execute(['domain' => $domainName]);
            }

            // Admin roles and relay hosts of the domain and of its mailboxes
            $stmt = $pdo->prepare("DELETE FROM domain_admins WHERE username LIKE :pattern");
            $stmt->execute(['pattern' => '%@' . $domainName]);
            $stmt = $pdo->prepare("DELETE FROM sender_relayhost WHERE account = :domainAccount OR account LIKE :pattern");
            $stmt->execute(['domainAccount' => '@' . $domainName, 'pattern' => '%@' . $domainName]);

            // Delete used_quota entries for this domain's users
            $stmt = $pdo->prepare("DELETE FROM used_quota WHERE SPLIT_PART(username, '@', 2) = :domain");
            $stmt->execute(['domain' => $domainName]);

            // Delete domain alias entries referencing this domain
            $stmt = $pdo->prepare("DELETE FROM alias_domain WHERE alias_domain = :d1 OR target_domain = :d2");
            $stmt->execute(['d1' => $domainName, 'd2' => $domainName]);

            // Delete the domain itself
            $stmt = $pdo->prepare("DELETE FROM domain WHERE domain = :domain");
            $stmt->execute(['domain' => $domainName]);

            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw new \RuntimeException("Failed to delete domain '{$domainName}': " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function enableDisableDomain(string $domainName, bool $active): void
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare("UPDATE domain SET active = :active, modified = NOW() WHERE domain = :domain");
        $stmt->execute(['active' => $active ? 1 : 0, 'domain' => $domainName]);

        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException("Domain '{$domainName}' not found");
        }
    }

    public function getDomainQuotaUsage(string $domainName): int
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(bytes), 0) AS \"totalBytes\" FROM used_quota WHERE SPLIT_PART(username, '@', 2) = :domain"
        );
        $stmt->execute(['domain' => $domainName]);
        $row = $stmt->fetch();

        return (int) (($row['totalBytes'] ?? 0) / 1048576);
    }

    public function supportsDomainQuota(): bool
    {
        return true;
    }
}
