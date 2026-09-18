<?php

declare(strict_types=1);

namespace App\Repositories\Pgsql;

use App\Exceptions\BackendConnectionException;
use App\Repositories\DomainOwnershipRepositoryInterface;

class PgsqlDomainOwnershipRepository implements DomainOwnershipRepositoryInterface
{
    public function getPendingDomains(): array
    {
        $stmt = $this->pdo()->query(
            "SELECT admin, domain, verify_code, verified, expire
             FROM domain_ownership ORDER BY domain"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function addPendingDomain(string $admin, string $domain, string $verifyCode, int $expireTimestamp): bool
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO domain_ownership (admin, domain, verify_code, verified, expire)
             VALUES (:admin, :domain, :code, 0, :expire)"
        );
        return $stmt->execute([
            'admin' => $admin,
            'domain' => $domain,
            'code' => $verifyCode,
            'expire' => $expireTimestamp,
        ]);
    }

    public function getVerifyCode(string $domain): ?string
    {
        $stmt = $this->pdo()->prepare("SELECT verify_code FROM domain_ownership WHERE domain = :domain LIMIT 1");
        $stmt->execute(['domain' => $domain]);
        $row = $stmt->fetch();
        return $row !== false ? $row['verify_code'] : null;
    }

    public function markVerified(string $domain): bool
    {
        $stmt = $this->pdo()->prepare("UPDATE domain_ownership SET verified = 1 WHERE domain = :domain");
        return $stmt->execute(['domain' => $domain]);
    }

    public function isVerified(string $domain): bool
    {
        $stmt = $this->pdo()->prepare("SELECT verified FROM domain_ownership WHERE domain = :domain LIMIT 1");
        $stmt->execute(['domain' => $domain]);
        $row = $stmt->fetch();
        return $row !== false && (bool) $row['verified'];
    }

    public function deletePendingDomain(string $domain): bool
    {
        $stmt = $this->pdo()->prepare("DELETE FROM domain_ownership WHERE domain = :domain");
        return $stmt->execute(['domain' => $domain]);
    }

    public function verifyDnsTxt(string $domain, string $verifyCode): bool
    {
        $records = @dns_get_record($domain, DNS_TXT);
        if ($records === false || empty($records)) {
            return false;
        }

        foreach ($records as $record) {
            $txt = $record['txt'] ?? '';
            if (str_contains($txt, $verifyCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws BackendConnectionException when the iredadmin database is not
     *                                    reachable, so a failed check never counts as verified
     */
    private function pdo(): \PDO
    {
        $pdo = IredadminPgsqlConnection::getInstance()->getPdo();
        if ($pdo === null) {
            throw new BackendConnectionException('iRedAdmin database not available');
        }
        return $pdo;
    }
}
