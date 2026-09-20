<?php

declare(strict_types=1);

namespace Tests\Repositories;

use App\Repositories\SqlSmtpSessions;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * Runs the shared SMTP session SQL against in-memory SQLite.
 */
#[RequiresPhpExtension('pdo_sqlite')]
class SqlSmtpSessionsTest extends TestCase
{
    private \PDO $pdo;
    private SqlSmtpSessions $sessions;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->createTable();
        $this->sessions = new SqlSmtpSessions($this->pdo);
    }

    public function testPaginatedReturnsTheNewestRowsFirst(): void
    {
        $this->seed();

        $result = $this->sessions->paginated(1, 2);

        $this->assertSame(4, $result->totalCount);
        $this->assertCount(2, $result->items);
        $this->assertSame('4', (string) $result->items[0]['id']);
        $this->assertSame('3', (string) $result->items[1]['id']);
    }

    public function testTheSecondPageHoldsTheOlderRows(): void
    {
        $this->seed();

        $result = $this->sessions->paginated(2, 2);

        $this->assertSame('2', (string) $result->items[0]['id']);
        $this->assertSame('1', (string) $result->items[1]['id']);
    }

    /**
     * MySQL refuses a statement that uses one named parameter more than once, so no
     * placeholder of the WHERE clause may repeat.
     */
    public function testEveryFilterPlaceholderIsUsedOnce(): void
    {
        $filter = new \ReflectionMethod(SqlSmtpSessions::class, 'filter');
        [$where, $params] = $filter->invoke($this->sessions, 'REJECT', 'boss@test.com', '10.0.0.1');

        $this->assertCount(5, $params);
        foreach (array_keys($params) as $name) {
            $this->assertSame(1, substr_count($where, ':' . $name), "placeholder :{$name} repeats");
        }
    }

    public function testActionFilterKeepsOnlyThatAction(): void
    {
        $this->seed();

        $result = $this->sessions->paginated(1, 20, 'REJECT');

        $this->assertSame(2, $result->totalCount);
        $this->assertSame(['REJECT', 'REJECT'], array_column($result->items, 'action'));
    }

    public function testEmailFilterMatchesSenderRecipientAndSaslUser(): void
    {
        $this->seed();

        $this->assertSame(2, $this->sessions->paginated(1, 20, null, 'spam@outside.test')->totalCount);
        $this->assertSame(2, $this->sessions->paginated(1, 20, null, 'boss@test.com')->totalCount);
        $this->assertSame(1, $this->sessions->paginated(1, 20, null, 'sales@test.com')->totalCount);
    }

    public function testEmailFilterIsCaseInsensitive(): void
    {
        $this->seed();

        $this->assertSame(2, $this->sessions->paginated(1, 20, null, 'Spam@Outside.TEST')->totalCount);
    }

    public function testClientAddressFilterMatchesExactly(): void
    {
        $this->seed();

        $this->assertSame(2, $this->sessions->paginated(1, 20, null, null, '198.51.100.7')->totalCount);
        $this->assertSame(0, $this->sessions->paginated(1, 20, null, null, '198.51.100')->totalCount);
    }

    public function testFiltersCombineWithAnd(): void
    {
        $this->seed();

        $result = $this->sessions->paginated(1, 20, 'REJECT', 'boss@test.com', '198.51.100.7');

        $this->assertSame(1, $result->totalCount);
        $this->assertSame('1', (string) $result->items[0]['id']);
    }

    public function testAnUnknownFilterValueReturnsNoRow(): void
    {
        $this->seed();

        $result = $this->sessions->paginated(1, 20, null, 'nobody@test.com');

        $this->assertSame(0, $result->totalCount);
        $this->assertSame([], $result->items);
    }

    public function testFindReturnsTheWholeRow(): void
    {
        $this->seed();

        $row = $this->sessions->find(1);

        $this->assertNotNull($row);
        $this->assertSame('REJECT', $row['action']);
        $this->assertSame('mx.outside.test', $row['reverse_client_name']);
        $this->assertSame('spam@outside.test', $row['sender']);
        $this->assertSame('boss@test.com', $row['recipient']);
        $this->assertArrayHasKey('encryption_cipher', $row);
    }

    public function testFindReturnsNullForAnUnknownId(): void
    {
        $this->seed();

        $this->assertNull($this->sessions->find(999));
    }

    public function testActionsListsEveryStoredActionOnce(): void
    {
        $this->seed();

        $this->assertSame(['DUNNO', 'OK', 'REJECT'], $this->sessions->actions());
    }

    public function testIsAvailableAnswersForTheTable(): void
    {
        $this->assertTrue($this->sessions->isAvailable());

        $this->pdo->exec('DROP TABLE smtp_sessions');
        $this->assertFalse($this->sessions->isAvailable());
    }

    private function createTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE smtp_sessions (
                id INTEGER PRIMARY KEY, time TEXT DEFAULT '', time_num INTEGER DEFAULT 0,
                action TEXT DEFAULT '', reason TEXT DEFAULT '', instance TEXT DEFAULT '',
                client_address TEXT DEFAULT '', client_name TEXT DEFAULT '',
                reverse_client_name TEXT DEFAULT '', helo_name TEXT DEFAULT '',
                sender TEXT DEFAULT '', sender_domain TEXT DEFAULT '',
                sasl_username TEXT DEFAULT '', sasl_domain TEXT DEFAULT '',
                recipient TEXT DEFAULT '', recipient_domain TEXT DEFAULT '',
                encryption_protocol TEXT DEFAULT '', encryption_cipher TEXT DEFAULT '',
                server_address TEXT DEFAULT '', server_port TEXT DEFAULT '')"
        );
    }

    private function seed(): void
    {
        $this->pdo->exec(
            "INSERT INTO smtp_sessions
             (id, action, reason, client_address, reverse_client_name, sender, recipient, sasl_username, encryption_cipher) VALUES
             (1, 'REJECT', 'Blacklisted', '198.51.100.7', 'mx.outside.test', 'spam@outside.test', 'boss@test.com', '', 'AES256'),
             (2, 'REJECT', 'Greylisted', '198.51.100.7', 'mx.outside.test', 'spam@outside.test', 'other@test.com', '', ''),
             (3, 'DUNNO', '', '203.0.113.4', 'mail.partner.test', 'partner@partner.test', 'boss@test.com', '', ''),
             (4, 'OK', 'Whitelisted', '192.0.2.10', '', 'sales@test.com', 'client@outside.test', 'sales@test.com', 'AES128')"
        );
    }
}
