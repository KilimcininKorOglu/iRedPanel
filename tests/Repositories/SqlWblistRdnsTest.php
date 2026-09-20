<?php

declare(strict_types=1);

namespace Tests\Repositories;

use App\Repositories\SqlWblistRdns;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * Runs the shared reverse DNS white/blacklist SQL against in-memory SQLite.
 */
#[RequiresPhpExtension('pdo_sqlite')]
class SqlWblistRdnsTest extends TestCase
{
    private \PDO $pdo;
    private SqlWblistRdns $wblist;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->pdo->exec("CREATE TABLE wblist_rdns (id INTEGER PRIMARY KEY, rdns TEXT UNIQUE, wb TEXT DEFAULT 'B')");
        $this->wblist = new SqlWblistRdns($this->pdo);
    }

    public function testReplaceStoresBothLists(): void
    {
        $this->wblist->replace(['Mail.Partner.Test'], ['mx.spam.test', 'mx2.spam.test']);

        $this->assertSame(
            ['whitelists' => ['mail.partner.test'], 'blacklists' => ['mx.spam.test', 'mx2.spam.test']],
            $this->wblist->all(),
        );
    }

    public function testReplaceDropsTheEarlierNames(): void
    {
        $this->wblist->replace([], ['mx.spam.test']);
        $this->wblist->replace([], ['mx.other.test']);

        $this->assertSame(['whitelists' => [], 'blacklists' => ['mx.other.test']], $this->wblist->all());
    }

    public function testAddStoresOneNameAndKeepsTheRest(): void
    {
        $this->wblist->replace([], ['mx.spam.test']);

        $this->assertTrue($this->wblist->add('MX.Other.Test', 'B'));
        $this->assertSame(['mx.other.test', 'mx.spam.test'], $this->wblist->all()['blacklists']);
    }

    public function testAddAnswersFalseForAStoredName(): void
    {
        $this->assertTrue($this->wblist->add('mx.spam.test', 'B'));
        $this->assertFalse($this->wblist->add('mx.spam.test', 'B'));
        $this->assertFalse($this->wblist->add('mx.spam.test', 'W'));
        $this->assertSame(['whitelists' => [], 'blacklists' => ['mx.spam.test']], $this->wblist->all());
    }

    public function testAddRefusesAnEmptyName(): void
    {
        $this->assertFalse($this->wblist->add('   ', 'B'));
        $this->assertSame(['whitelists' => [], 'blacklists' => []], $this->wblist->all());
    }

    public function testAddWritesTheWhitelistForW(): void
    {
        $this->assertTrue($this->wblist->add('mail.partner.test', 'W'));

        $this->assertSame(['mail.partner.test'], $this->wblist->all()['whitelists']);
    }
}
