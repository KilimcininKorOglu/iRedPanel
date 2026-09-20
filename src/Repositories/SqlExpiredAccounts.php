<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Utils\ExpiryDate;

/**
 * The expired accounts of the vmail database, for MySQL and PostgreSQL. The SQL is
 * the same on both backends, so both repositories share this class.
 *
 * A mailbox admin holds its expiry date in the mailbox row, so the mailbox list
 * already carries it and the admin list holds the standalone admins only.
 */
final class SqlExpiredAccounts implements ExpiredAccountRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function expiredMailboxes(?int $now = null): array
    {
        return $this->addresses('SELECT username FROM mailbox WHERE active = 1 AND expired < :cutoff ORDER BY username', $now);
    }

    public function expiredDomains(?int $now = null): array
    {
        return $this->addresses('SELECT domain FROM domain WHERE active = 1 AND expired < :cutoff ORDER BY domain', $now);
    }

    public function expiredAdmins(?int $now = null): array
    {
        return $this->addresses('SELECT username FROM admin WHERE active = 1 AND expired < :cutoff ORDER BY username', $now);
    }

    public function mailboxCounts(?int $now = null): array
    {
        $now ??= time();
        $cutoff = ExpiryDate::sqlCutoff($now);
        $soon = ExpiryDate::sqlCutoff($now + ExpiryDate::SOON_DAYS * 86400);

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM mailbox WHERE expired < :cutoff'
        );
        $stmt->execute(['cutoff' => $cutoff]);
        $expired = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM mailbox WHERE expired >= :cutoff AND expired < :soon'
        );
        $stmt->execute(['cutoff' => $cutoff, 'soon' => $soon]);

        return ['expired' => $expired, 'expiring' => (int) $stmt->fetchColumn()];
    }

    /**
     * @return list<string>
     */
    private function addresses(string $sql, ?int $now): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['cutoff' => ExpiryDate::sqlCutoff($now)]);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
