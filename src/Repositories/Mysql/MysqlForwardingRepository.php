<?php

declare(strict_types=1);

namespace App\Repositories\Mysql;

use App\Repositories\ForwardingRepositoryInterface;

class MysqlForwardingRepository implements ForwardingRepositoryInterface
{
    public function getForwardings(string $email): array
    {
        $pdo = MysqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "SELECT forwarding FROM forwardings
             WHERE address = :address AND forwarding != :self AND is_forwarding = 1
             ORDER BY forwarding"
        );
        $stmt->execute(['address' => $email, 'self' => $email]);

        $forwardings = [];
        while ($row = $stmt->fetch()) {
            $forwardings[] = $row['forwarding'];
        }

        return $forwardings;
    }

    public function setForwardings(string $email, string $domain, array $forwardingAddresses): void
    {
        $pdo = MysqlConnection::getInstance()->getPdo();

        // Delete existing forwardings (except self-forwarding)
        $stmt = $pdo->prepare(
            "DELETE FROM forwardings WHERE address = :address AND forwarding != :self AND is_forwarding = 1"
        );
        $stmt->execute(['address' => $email, 'self' => $email]);

        // Insert new forwardings with the status of the mailbox, so a disabled mailbox does not forward mail
        if (!empty($forwardingAddresses)) {
            $stmt = $pdo->prepare(
                "INSERT INTO forwardings (address, forwarding, domain, dest_domain, is_forwarding, active)
                 VALUES (:address, :forwarding, :domain, :destDomain, 1,
                         (SELECT active FROM mailbox WHERE username = :owner))"
            );
            foreach ($forwardingAddresses as $forward) {
                $forward = trim($forward);
                if ($forward === '' || $forward === $email) {
                    continue;
                }
                $destDomain = str_contains($forward, '@') ? explode('@', $forward, 2)[1] : $domain;
                $stmt->execute([
                    'address' => $email,
                    'forwarding' => $forward,
                    'domain' => $domain,
                    'destDomain' => $destDomain,
                    'owner' => $email,
                ]);
            }
        }
    }

    public function getKeepCopy(string $email): bool
    {
        $pdo = MysqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare(
            "SELECT 1 FROM forwardings
             WHERE address = :address AND forwarding = :self AND is_forwarding = 1
             LIMIT 1"
        );
        $stmt->execute(['address' => $email, 'self' => $email]);

        return $stmt->fetch() !== false;
    }

    public function setKeepCopy(string $email, string $domain, bool $keepCopy): void
    {
        $pdo = MysqlConnection::getInstance()->getPdo();

        // A mailbox without external forwardings needs its self row for local delivery
        if ($keepCopy || $this->getForwardings($email) === []) {
            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO forwardings (address, forwarding, domain, dest_domain, is_forwarding, active)
                 VALUES (:address, :self, :domain, :domain2, 1,
                         (SELECT active FROM mailbox WHERE username = :owner))"
            );
            $stmt->execute(['address' => $email, 'self' => $email, 'domain' => $domain, 'domain2' => $domain, 'owner' => $email]);
        } else {
            $stmt = $pdo->prepare(
                "DELETE FROM forwardings WHERE address = :address AND forwarding = :self AND is_forwarding = 1"
            );
            $stmt->execute(['address' => $email, 'self' => $email]);
        }
    }
}
