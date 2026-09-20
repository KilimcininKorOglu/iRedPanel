<?php

declare(strict_types=1);

namespace Tests\Repositories;

use App\Repositories\SqlMailboxSharing;
use PHPUnit\Framework\TestCase;

/**
 * Runs the shared folder SQL against in-memory SQLite.
 */
class SqlMailboxSharingTest extends TestCase
{
    private \PDO $pdo;
    private SqlMailboxSharing $sharing;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->pdo->exec("CREATE TABLE share_folder (from_user TEXT, to_user TEXT, dummy TEXT)");
        $this->pdo->exec("CREATE TABLE anyone_shares (from_user TEXT PRIMARY KEY, dummy TEXT)");
        $this->pdo->exec(
            "INSERT INTO share_folder (from_user, to_user) VALUES
             ('a@test.com', 'c@test.com'), ('a@test.com', 'b@test.com'), ('d@test.com', 'a@test.com')"
        );
        $this->sharing = new SqlMailboxSharing($this->pdo);
    }

    public function testTheSharesOfAMailboxAreListedInAddressOrder(): void
    {
        $this->assertSame(['b@test.com', 'c@test.com'], $this->sharing->sharedWith('a@test.com'));
    }

    public function testTheMailboxesThatShareWithItAreListed(): void
    {
        $this->assertSame(['d@test.com'], $this->sharing->sharedBy('a@test.com'));
    }

    public function testAMailboxWithoutSharesAnswersEmptyLists(): void
    {
        $this->assertSame([], $this->sharing->sharedWith('x@test.com'));
        $this->assertSame([], $this->sharing->sharedBy('x@test.com'));
    }

    public function testRevokeRemovesOnlyTheOnePair(): void
    {
        $this->sharing->revoke('a@test.com', 'b@test.com');

        $this->assertSame(['c@test.com'], $this->sharing->sharedWith('a@test.com'));
        $this->assertSame(['d@test.com'], $this->sharing->sharedBy('a@test.com'));
    }

    public function testRevokeOfAnUnknownPairChangesNothing(): void
    {
        $this->sharing->revoke('a@test.com', 'x@test.com');

        $this->assertCount(2, $this->sharing->sharedWith('a@test.com'));
    }

    public function testTheShareWithEveryAccountIsReadAndRevoked(): void
    {
        $this->pdo->exec("INSERT INTO anyone_shares (from_user) VALUES ('a@test.com')");
        $this->assertTrue($this->sharing->sharesWithAnyone('a@test.com'));
        $this->assertFalse($this->sharing->sharesWithAnyone('b@test.com'));

        $this->sharing->revokeAnyone('a@test.com');

        $this->assertFalse($this->sharing->sharesWithAnyone('a@test.com'));
        // The pair rows of the mailbox stay, because they are separate shares.
        $this->assertCount(2, $this->sharing->sharedWith('a@test.com'));
    }
}
