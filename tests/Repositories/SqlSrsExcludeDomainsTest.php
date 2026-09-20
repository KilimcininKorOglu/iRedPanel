<?php

declare(strict_types=1);

namespace Tests\Repositories;

use App\Repositories\SqlSrsExcludeDomains;
use PHPUnit\Framework\TestCase;

/**
 * Runs the shared SRS exclude domain SQL against in-memory SQLite.
 */
class SqlSrsExcludeDomainsTest extends TestCase
{
    private \PDO $pdo;
    private SqlSrsExcludeDomains $srs;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->pdo->exec("CREATE TABLE srs_exclude_domains (id INTEGER PRIMARY KEY, domain TEXT UNIQUE)");
        $this->srs = new SqlSrsExcludeDomains($this->pdo);
    }

    public function testAnEmptyTableHasNoDomain(): void
    {
        $this->assertSame([], $this->srs->domains());
    }

    public function testTheStoredDomainsComeBackSorted(): void
    {
        $this->pdo->exec("INSERT INTO srs_exclude_domains (domain) VALUES ('b.test'), ('a.test')");

        $this->assertSame(['a.test', 'b.test'], $this->srs->domains());
    }

    public function testSetDomainsAddsTheNewOnesOnly(): void
    {
        $this->srs->setDomains(['a.test', 'b.test']);
        $this->srs->setDomains(['a.test', 'b.test', 'c.test']);

        $this->assertSame(['a.test', 'b.test', 'c.test'], $this->srs->domains());
    }

    public function testSetDomainsRemovesTheMissingOnes(): void
    {
        $this->srs->setDomains(['a.test', 'b.test', 'c.test']);
        $this->srs->setDomains(['b.test']);

        $this->assertSame(['b.test'], $this->srs->domains());
    }

    public function testAnEmptyListClearsTheTable(): void
    {
        $this->srs->setDomains(['a.test']);
        $this->srs->setDomains([]);

        $this->assertSame([], $this->srs->domains());
    }

    public function testAFailedWriteLeavesTheStoredListUnchanged(): void
    {
        $this->srs->setDomains(['a.test']);
        $this->pdo->exec("DROP TABLE srs_exclude_domains");

        $this->expectException(\PDOException::class);
        $this->srs->setDomains(['a.test', 'b.test']);
    }
}
