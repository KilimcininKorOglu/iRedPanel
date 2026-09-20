<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * The reverse DNS white and black list of iRedAPD (table `wblist_rdns`), for
 * MySQL and PostgreSQL. The SQL is the same on both backends, so both
 * repositories share this class.
 */
final class SqlWblistRdns
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * The stored names, split into the two lists.
     *
     * @return array{whitelists: list<string>, blacklists: list<string>}
     */
    public function all(): array
    {
        $whitelists = [];
        $blacklists = [];

        $stmt = $this->pdo->query('SELECT rdns, wb FROM wblist_rdns ORDER BY rdns');
        while ($row = $stmt->fetch()) {
            if ($row['wb'] === 'W') {
                $whitelists[] = (string) $row['rdns'];
            } else {
                $blacklists[] = (string) $row['rdns'];
            }
        }

        return ['whitelists' => $whitelists, 'blacklists' => $blacklists];
    }

    /**
     * Replaces both lists in one transaction, so a failed insert leaves the
     * stored lists unchanged.
     *
     * @param list<string> $whitelists
     * @param list<string> $blacklists
     */
    public function replace(array $whitelists, array $blacklists): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM wblist_rdns');
            $stmt = $this->pdo->prepare('INSERT INTO wblist_rdns (rdns, wb) VALUES (:rdns, :wb)');
            foreach (['W' => $whitelists, 'B' => $blacklists] as $wb => $names) {
                $this->insertNames($stmt, $names, (string) $wb);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Adds one name to a list without touching the rest of it.
     *
     * @param string $wb W for the whitelist, anything else for the blacklist
     * @return bool false when the name is already stored, in either list
     */
    public function add(string $rdns, string $wb): bool
    {
        $rdns = strtolower(trim($rdns));
        if ($rdns === '') {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $added = $this->insertMissing($rdns, $wb === 'W' ? 'W' : 'B');
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $added;
    }

    /**
     * The name is read back inside the transaction, because `rdns` is unique and
     * an insert of a stored name would fail instead of answering false.
     */
    private function insertMissing(string $rdns, string $wb): bool
    {
        $stmt = $this->pdo->prepare('SELECT wb FROM wblist_rdns WHERE rdns = :rdns');
        $stmt->execute(['rdns' => $rdns]);
        if ($stmt->fetch() !== false) {
            return false;
        }

        $this->pdo->prepare('INSERT INTO wblist_rdns (rdns, wb) VALUES (:rdns, :wb)')
            ->execute(['rdns' => $rdns, 'wb' => $wb]);

        return true;
    }

    /**
     * @param list<string> $names
     */
    private function insertNames(\PDOStatement $stmt, array $names, string $wb): void
    {
        foreach ($names as $rdns) {
            $rdns = trim($rdns);
            if ($rdns !== '') {
                $stmt->execute(['rdns' => strtolower($rdns), 'wb' => $wb]);
            }
        }
    }
}
