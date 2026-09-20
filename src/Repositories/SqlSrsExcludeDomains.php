<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * The SRS exclude domains of the iredapd database, for MySQL and PostgreSQL. The
 * SQL is the same on both backends, so both repositories share this class.
 *
 * iRedAPD reads the table before it rewrites a sender address with SRS. A domain
 * in the table keeps its own envelope sender, so the panel only stores the list.
 */
final class SqlSrsExcludeDomains
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /** @return list<string> */
    public function domains(): array
    {
        return $this->pdo->query("SELECT domain FROM srs_exclude_domains ORDER BY domain")
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Stores the excluded domains. The table is the whole list, so a domain that
     * the form does not carry loses its exclusion.
     *
     * @param list<string> $domains
     */
    public function setDomains(array $domains): void
    {
        $stored = $this->domains();
        $this->pdo->beginTransaction();
        try {
            $this->insert(array_diff($domains, $stored));
            $this->delete(array_diff($stored, $domains));
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @param iterable<string> $domains */
    private function insert(iterable $domains): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO srs_exclude_domains (domain) VALUES (:domain)");
        foreach ($domains as $domain) {
            $stmt->execute(['domain' => $domain]);
        }
    }

    /** @param iterable<string> $domains */
    private function delete(iterable $domains): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM srs_exclude_domains WHERE domain = :domain");
        foreach ($domains as $domain) {
            $stmt->execute(['domain' => $domain]);
        }
    }
}
