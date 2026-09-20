<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Greylisting rows of the iredapd database, for MySQL and PostgreSQL. The SQL
 * is the same on both backends, so both repositories share this class.
 */
final class SqlGreylisting
{
    /** Comment prefix of the sender rows that the SPF job writes per whitelisted domain. */
    public const SPF_COMMENT_PREFIX = 'AUTO-UPDATE: ';

    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * Every account that has an own greylisting setting.
     *
     * @return list<array{account: string, active: bool}>
     */
    public function accounts(): array
    {
        $stmt = $this->pdo->query(
            "SELECT account, active FROM greylisting WHERE sender = '@.' ORDER BY account"
        );

        return array_map(
            static fn (array $row): array => ['account' => $row['account'], 'active' => (bool) $row['active']],
            $stmt->fetchAll(),
        );
    }

    /**
     * Removes the greylisting setting of the account and its whitelisted senders.
     *
     * @return int the number of removed rows
     */
    public function deleteSettings(string $account): int
    {
        $removed = 0;
        foreach (['greylisting', 'greylisting_whitelists'] as $table) {
            $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE account = :account");
            $stmt->execute(['account' => $account]);
            $removed += $stmt->rowCount();
        }

        return $removed;
    }

    /**
     * @param list<string> $senders
     * @return int the number of senders that the account did not have yet
     */
    public function addWhitelistedSenders(string $account, array $senders): int
    {
        $stored = $this->whitelistedSenders($account);
        $stmt = $this->pdo->prepare(
            "INSERT INTO greylisting_whitelists (account, sender, comment) VALUES (:account, :sender, '')"
        );

        $added = 0;
        foreach (array_diff($senders, $stored) as $sender) {
            $stmt->execute(['account' => $account, 'sender' => $sender]);
            $added++;
        }

        return $added;
    }

    /**
     * @param list<string> $senders
     * @return int the number of removed rows
     */
    public function removeWhitelistedSenders(string $account, array $senders): int
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM greylisting_whitelists WHERE account = :account AND sender = :sender"
        );

        $removed = 0;
        foreach ($senders as $sender) {
            $stmt->execute(['account' => $account, 'sender' => $sender]);
            $removed += $stmt->rowCount();
        }

        return $removed;
    }

    /** @return list<string> */
    public function whitelistedSenders(string $account): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT sender FROM greylisting_whitelists WHERE account = :account ORDER BY sender"
        );
        $stmt->execute(['account' => $account]);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * The domains whose SPF records the iRedAPD job resolves into whitelisted senders.
     *
     * @return list<string>
     */
    public function whitelistDomains(): array
    {
        return $this->pdo->query("SELECT domain FROM greylisting_whitelist_domains ORDER BY domain")
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Stores the whitelisted domains. A removed domain loses the senders that the
     * SPF job resolved for it, because no job removes them later.
     *
     * @param list<string> $domains
     */
    public function setWhitelistDomains(array $domains): void
    {
        $stored = $this->whitelistDomains();
        $this->pdo->beginTransaction();
        try {
            $this->insertDomains(array_diff($domains, $stored));
            $this->deleteDomains(array_diff($stored, $domains));
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * The senders that the SPF job resolved, keyed by the domain they come from.
     *
     * @return array<string, list<string>>
     */
    public function spfSenders(): array
    {
        $stmt = $this->pdo->query(
            "SELECT sender, comment FROM greylisting_whitelist_domain_spf ORDER BY comment, sender"
        );

        $senders = [];
        foreach ($stmt->fetchAll() as $row) {
            $domain = str_starts_with((string) $row['comment'], self::SPF_COMMENT_PREFIX)
                ? substr((string) $row['comment'], strlen(self::SPF_COMMENT_PREFIX))
                : (string) $row['comment'];
            $senders[$domain][] = (string) $row['sender'];
        }

        return $senders;
    }

    /** @param iterable<string> $domains */
    private function insertDomains(iterable $domains): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO greylisting_whitelist_domains (domain) VALUES (:domain)");
        foreach ($domains as $domain) {
            $stmt->execute(['domain' => $domain]);
        }
    }

    /** @param iterable<string> $domains */
    private function deleteDomains(iterable $domains): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM greylisting_whitelist_domains WHERE domain = :domain");
        $spf = $this->pdo->prepare("DELETE FROM greylisting_whitelist_domain_spf WHERE comment = :comment");
        foreach ($domains as $domain) {
            $stmt->execute(['domain' => $domain]);
            $spf->execute(['comment' => self::SPF_COMMENT_PREFIX . $domain]);
        }
    }
}
