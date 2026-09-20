<?php

declare(strict_types=1);

namespace App\Repositories\Pgsql;

use App\Repositories\DashboardRepositoryInterface;
use App\Repositories\RepositoryFactory;

class PgsqlDashboardRepository implements DashboardRepositoryInterface
{
    public function getStats(): array
    {
        $pdo = PgsqlConnection::getInstance()->getPdo();

        $stats = [
            'totalDomains' => 0,
            'activeDomains' => 0,
            'totalUsers' => 0,
            'activeUsers' => 0,
            'totalAdmins' => 0,
            'totalQuotaAllocated' => 0,
            'totalQuotaUsed' => 0,
            'totalMessages' => 0,
            'expiredUsers' => 0,
            'expiringUsers' => 0,
        ];

        $row = $pdo->query("SELECT COUNT(*) AS c FROM domain")->fetch();
        $stats['totalDomains'] = (int) $row['c'];

        $row = $pdo->query("SELECT COUNT(*) AS c FROM domain WHERE active = 1")->fetch();
        $stats['activeDomains'] = (int) $row['c'];

        $row = $pdo->query("SELECT COUNT(*) AS c FROM mailbox")->fetch();
        $stats['totalUsers'] = (int) $row['c'];

        $row = $pdo->query("SELECT COUNT(*) AS c FROM mailbox WHERE active = 1")->fetch();
        $stats['activeUsers'] = (int) $row['c'];

        // Same set as AdminRepository::getAdmins(): standalone plus mailbox-based admins.
        $row = $pdo->query(
            "SELECT COUNT(*) AS c FROM (
                 SELECT username FROM admin
                 UNION
                 SELECT username FROM mailbox WHERE isadmin = 1 OR isglobaladmin = 1
             ) admins"
        )->fetch();
        $stats['totalAdmins'] = (int) $row['c'];

        $row = $pdo->query("SELECT COALESCE(SUM(quota), 0) AS q FROM mailbox")->fetch();
        $stats['totalQuotaAllocated'] = (int) $row['q'];

        $row = $pdo->query("SELECT COALESCE(SUM(bytes), 0) AS b, COALESCE(SUM(messages), 0) AS m FROM used_quota")->fetch();
        $stats['totalQuotaUsed'] = (int) ($row['b'] / 1048576);
        $stats['totalMessages'] = (int) $row['m'];

        $counts = RepositoryFactory::getExpiredAccountRepository()->mailboxCounts();
        $stats['expiredUsers'] = $counts['expired'];
        $stats['expiringUsers'] = $counts['expiring'];

        return $stats;
    }
}
