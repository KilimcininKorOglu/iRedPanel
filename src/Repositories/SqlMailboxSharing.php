<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * The IMAP shared folder rows that Dovecot keeps: `share_folder` holds one row per
 * pair of mailboxes, `anyone_shares` holds the mailboxes that share with everyone.
 * Dovecot writes the rows, and it reads them as the authoritative list, so deleting
 * a row revokes the share.
 *
 * The SQL is the same on both backends. With the SQL backends the tables live in the
 * vmail database, with LDAP in the iredadmin database.
 */
final class SqlMailboxSharing
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * The mailboxes that this mailbox shares folders with.
     *
     * @return list<string>
     */
    public function sharedWith(string $email): array
    {
        return $this->addresses("SELECT to_user FROM share_folder WHERE from_user = :email ORDER BY to_user", $email);
    }

    /**
     * The mailboxes that share folders with this mailbox.
     *
     * @return list<string>
     */
    public function sharedBy(string $email): array
    {
        return $this->addresses("SELECT from_user FROM share_folder WHERE to_user = :email ORDER BY from_user", $email);
    }

    /** Whether this mailbox shares its folders with every account of the server. */
    public function sharesWithAnyone(string $email): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM anyone_shares WHERE from_user = :email");
        $stmt->execute(['email' => $email]);

        return $stmt->fetchColumn() !== false;
    }

    /** Revokes the share of one mailbox pair. */
    public function revoke(string $from, string $to): void
    {
        $this->pdo->prepare("DELETE FROM share_folder WHERE from_user = :from AND to_user = :to")
            ->execute(['from' => $from, 'to' => $to]);
    }

    /** Revokes the share with every account. */
    public function revokeAnyone(string $email): void
    {
        $this->pdo->prepare("DELETE FROM anyone_shares WHERE from_user = :email")->execute(['email' => $email]);
    }

    /**
     * @return list<string>
     */
    private function addresses(string $sql, string $email): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['email' => $email]);

        return array_map(strval(...), $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }
}
