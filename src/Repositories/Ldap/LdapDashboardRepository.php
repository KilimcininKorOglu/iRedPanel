<?php

declare(strict_types=1);

namespace App\Repositories\Ldap;

use App\Models\LdapConnection;
use App\Models\Settings;
use App\Repositories\DashboardRepositoryInterface;
use App\Repositories\Mysql\IredadminConnection;
use App\Repositories\RepositoryFactory;
use App\Utils\LdapUtils;

/**
 * Counts domains and mailboxes in LDAP. Dovecot writes the used quota into the
 * iredadmin database, so the used quota and message totals come from there.
 */
class LdapDashboardRepository implements DashboardRepositoryInterface
{
    /** Mailboxes without the catch-all entry `mail=@<domain>`, as in the user list. */
    private const MAILBOX_FILTER = '(&(objectClass=mailUser)(!(mail=@*)))';

    public function getStats(): array
    {
        $conn = LdapConnection::getInstance()->getConn();
        $baseDn = 'o=domains,' . Settings::getInstance()->ldapRootDn;
        $domains = LdapUtils::searchEntries($conn, $baseDn, '(objectClass=mailDomain)', ['accountStatus']);
        $mailboxes = LdapUtils::searchEntries($conn, $baseDn, self::MAILBOX_FILTER, ['accountStatus', 'mailQuota']);
        $allocatedBytes = array_sum(array_map(
            static fn (array $entry): int => (int) (LdapUtils::allValues($entry, 'mailQuota')[0] ?? 0),
            $mailboxes
        ));
        [$usedBytes, $messages] = self::usedQuota();
        $expiry = RepositoryFactory::getExpiredAccountRepository()->mailboxCounts();

        return [
            'totalDomains' => count($domains),
            'activeDomains' => self::countActive($domains),
            'totalUsers' => count($mailboxes),
            'activeUsers' => self::countActive($mailboxes),
            'totalAdmins' => count(RepositoryFactory::getAdminRepository()->getAdmins()),
            'totalQuotaAllocated' => intdiv($allocatedBytes, 1048576),
            'totalQuotaUsed' => intdiv($usedBytes, 1048576),
            'totalMessages' => $messages,
            'expiredUsers' => $expiry['expired'],
            'expiringUsers' => $expiry['expiring'],
        ];
    }

    private static function countActive(array $entries): int
    {
        return count(array_filter(
            $entries,
            static fn (array $entry): bool => (LdapUtils::allValues($entry, 'accountStatus')[0] ?? '') === 'active'
        ));
    }

    /**
     * Without an iredadmin database the used quota is unknown and counts as zero.
     *
     * @return array{0: int, 1: int} used bytes and stored messages
     */
    private static function usedQuota(): array
    {
        $pdo = IredadminConnection::getInstance()->getPdo();
        if ($pdo === null) {
            return [0, 0];
        }
        $row = $pdo->query('SELECT COALESCE(SUM(bytes), 0) AS b, COALESCE(SUM(messages), 0) AS m FROM used_quota')->fetch();

        return [(int) $row['b'], (int) $row['m']];
    }
}
