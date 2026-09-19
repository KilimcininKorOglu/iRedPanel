<?php

declare(strict_types=1);

namespace Tests\Repositories;

use App\Repositories\SqlGreylisting;
use PHPUnit\Framework\TestCase;

/**
 * Runs the shared greylisting SQL against in-memory SQLite.
 */
class SqlGreylistingTest extends TestCase
{
    private \PDO $pdo;
    private SqlGreylisting $greylisting;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->pdo->exec(
            "CREATE TABLE greylisting (id INTEGER PRIMARY KEY, account TEXT, priority INT DEFAULT 0,
             sender TEXT, sender_priority INT DEFAULT 0, comment TEXT DEFAULT '', active INT DEFAULT 1)"
        );
        $this->pdo->exec(
            "CREATE TABLE greylisting_whitelists (id INTEGER PRIMARY KEY, account TEXT, sender TEXT, comment TEXT DEFAULT '')"
        );
        $this->pdo->exec("CREATE TABLE greylisting_whitelist_domains (id INTEGER PRIMARY KEY, domain TEXT UNIQUE)");
        $this->pdo->exec(
            "CREATE TABLE greylisting_whitelist_domain_spf (id INTEGER PRIMARY KEY, account TEXT, sender TEXT, comment TEXT)"
        );
        $this->greylisting = new SqlGreylisting($this->pdo);
    }

    public function testAccountsListsTheOwnSettings(): void
    {
        $this->pdo->exec(
            "INSERT INTO greylisting (account, sender, active) VALUES
             ('@.', '@.', 1), ('user@test.com', '@.', 0), ('user@test.com', 'other@x.test', 1)"
        );

        $this->assertSame(
            [['account' => '@.', 'active' => true], ['account' => 'user@test.com', 'active' => false]],
            $this->greylisting->accounts(),
        );
    }

    public function testDeleteSettingsRemovesTheSendersOfTheAccountOnly(): void
    {
        $this->pdo->exec("INSERT INTO greylisting (account, sender) VALUES ('user@test.com', '@.'), ('@.', '@.')");
        $this->pdo->exec(
            "INSERT INTO greylisting_whitelists (account, sender) VALUES
             ('user@test.com', 'a@x.test'), ('user@test.com', 'b@x.test'), ('@.', 'c@x.test')"
        );

        $this->assertSame(3, $this->greylisting->deleteSettings('user@test.com'));
        $this->assertSame([['account' => '@.', 'active' => true]], $this->greylisting->accounts());
        $this->assertSame(['c@x.test'], $this->greylisting->whitelistedSenders('@.'));
    }

    public function testAddWhitelistedSendersSkipsTheStoredOnes(): void
    {
        $this->assertSame(2, $this->greylisting->addWhitelistedSenders('@.', ['a@x.test', 'b@x.test']));
        $this->assertSame(1, $this->greylisting->addWhitelistedSenders('@.', ['a@x.test', 'c@x.test']));
        $this->assertSame(['a@x.test', 'b@x.test', 'c@x.test'], $this->greylisting->whitelistedSenders('@.'));
    }

    public function testRemoveWhitelistedSendersCountsTheRemovedRows(): void
    {
        $this->greylisting->addWhitelistedSenders('@.', ['a@x.test', 'b@x.test']);

        $this->assertSame(1, $this->greylisting->removeWhitelistedSenders('@.', ['a@x.test', 'none@x.test']));
        $this->assertSame(['b@x.test'], $this->greylisting->whitelistedSenders('@.'));
    }

    /**
     * No job removes the senders of a domain that the admin takes off the list.
     */
    public function testSetWhitelistDomainsDropsTheSpfSendersOfARemovedDomain(): void
    {
        $this->greylisting->setWhitelistDomains(['one.test', 'two.test']);
        $stmt = $this->pdo->prepare(
            "INSERT INTO greylisting_whitelist_domain_spf (account, sender, comment) VALUES ('@.', :sender, :comment)"
        );
        $stmt->execute(['sender' => '1.2.3.0/24', 'comment' => SqlGreylisting::SPF_COMMENT_PREFIX . 'one.test']);
        $stmt->execute(['sender' => '5.6.7.8', 'comment' => SqlGreylisting::SPF_COMMENT_PREFIX . 'two.test']);

        $this->greylisting->setWhitelistDomains(['two.test', 'three.test']);

        $this->assertSame(['three.test', 'two.test'], $this->greylisting->whitelistDomains());
        $this->assertSame(['two.test' => ['5.6.7.8']], $this->greylisting->spfSenders());
    }
}
