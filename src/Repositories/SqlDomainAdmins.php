<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Domain admin rows of the vmail database, for MySQL and PostgreSQL. A mailbox that
 * administers a domain carries mailbox.isadmin=1 and a domain_admins row per domain;
 * a standalone admin has only the domain_admins rows.
 */
final class SqlDomainAdmins
{
    /**
     * @return list<string> the admins of the domain, lowercased and sorted
     */
    public static function list(\PDO $pdo, string $domain): array
    {
        $stmt = $pdo->prepare('SELECT LOWER(username) FROM domain_admins WHERE domain = :domain ORDER BY 1');
        $stmt->execute(['domain' => $domain]);

        return array_values(array_unique($stmt->fetchAll(\PDO::FETCH_COLUMN)));
    }

    /**
     * Sets the domain admin flag of a mailbox. A standalone admin has no mailbox row.
     */
    public static function markMailbox(\PDO $pdo, string $username): void
    {
        $pdo->prepare('UPDATE mailbox SET isadmin = 1 WHERE username = :username')
            ->execute(['username' => $username]);
    }

    /**
     * Clears the domain admin flag of a mailbox that administers no domain any more.
     */
    public static function unmarkMailboxWithoutDomains(\PDO $pdo, string $username): void
    {
        $pdo->prepare(
            "UPDATE mailbox SET isadmin = 0 WHERE username = :username
               AND NOT EXISTS (SELECT 1 FROM domain_admins WHERE username = :admin AND domain <> 'ALL')"
        )->execute(['username' => $username, 'admin' => $username]);
    }
}
