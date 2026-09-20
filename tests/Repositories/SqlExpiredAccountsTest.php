<?php

declare(strict_types=1);

namespace Tests\Repositories;

use App\Repositories\SqlExpiredAccounts;
use App\Utils\ExpiryDate;
use PHPUnit\Framework\TestCase;

/**
 * Runs the shared expired account SQL against in-memory SQLite.
 */
class SqlExpiredAccountsTest extends TestCase
{
    /** 2026-09-20 12:00:00 local time is the "now" of every test. */
    private int $now;
    private \PDO $pdo;
    private SqlExpiredAccounts $expired;

    protected function setUp(): void
    {
        $this->now = (int) strtotime('2026-09-20 12:00:00');
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->pdo->exec('CREATE TABLE mailbox (username TEXT PRIMARY KEY, active INTEGER, expired TEXT)');
        $this->pdo->exec('CREATE TABLE domain (domain TEXT PRIMARY KEY, active INTEGER, expired TEXT)');
        $this->pdo->exec('CREATE TABLE admin (username TEXT PRIMARY KEY, active INTEGER, expired TEXT)');
        $this->expired = new SqlExpiredAccounts($this->pdo);
    }

    private function addMailbox(string $username, int $active, string $expired): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO mailbox (username, active, expired) VALUES (?, ?, ?)');
        $stmt->execute([$username, $active, $expired]);
    }

    public function testAMailboxWithoutAnExpiryDateIsNeverListed(): void
    {
        $this->addMailbox('never@test.com', 1, ExpiryDate::SQL_NONE);

        $this->assertSame([], $this->expired->expiredMailboxes($this->now));
    }

    public function testTheExpiryDayItselfIsStillValid(): void
    {
        $this->addMailbox('today@test.com', 1, '2026-09-20 00:00:00');

        $this->assertSame([], $this->expired->expiredMailboxes($this->now));
    }

    public function testTheDayAfterTheExpiryDateIsExpired(): void
    {
        $this->addMailbox('yesterday@test.com', 1, '2026-09-19 00:00:00');

        $this->assertSame(['yesterday@test.com'], $this->expired->expiredMailboxes($this->now));
    }

    public function testADisabledMailboxIsNotListedAgain(): void
    {
        $this->addMailbox('off@test.com', 0, '2026-01-01 00:00:00');

        $this->assertSame([], $this->expired->expiredMailboxes($this->now));
    }

    public function testTheCardCountsExpiredAndExpiringMailboxes(): void
    {
        $this->addMailbox('gone@test.com', 0, '2026-09-19 00:00:00');
        $this->addMailbox('today@test.com', 1, '2026-09-20 00:00:00');
        $this->addMailbox('soon@test.com', 1, '2026-10-19 00:00:00');
        $this->addMailbox('later@test.com', 1, '2026-10-20 00:00:00');
        $this->addMailbox('never@test.com', 1, ExpiryDate::SQL_NONE);

        // The disabled mailbox counts too, and the expiry day itself is still valid.
        $this->assertSame(['expired' => 1, 'expiring' => 2], $this->expired->mailboxCounts($this->now));
    }

    public function testAnExpiredDomainAndAdminAreListed(): void
    {
        $this->pdo->exec("INSERT INTO domain (domain, active, expired) VALUES ('old.test', 1, '2026-09-19 00:00:00'), ('new.test', 1, '2027-01-01 00:00:00')");
        $this->pdo->exec("INSERT INTO admin (username, active, expired) VALUES ('a@old.test', 1, '2025-05-05 00:00:00')");

        $this->assertSame(['old.test'], $this->expired->expiredDomains($this->now));
        $this->assertSame(['a@old.test'], $this->expired->expiredAdmins($this->now));
    }
}
