<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\PasswordResets;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * Runs the password reset SQL against in-memory SQLite.
 */
#[RequiresPhpExtension('pdo_sqlite')]
class PasswordResetsTest extends TestCase
{
    private \PDO $pdo;
    private PasswordResets $resets;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $this->resets = new PasswordResets($this->pdo);
        $this->resets->ensureTableExists();
    }

    public function testCreateReturnsATokenThatFindsTheMailbox(): void
    {
        $now = 1_700_000_000;
        $token = $this->resets->create('user@example.com', $now);

        $this->assertNotNull($token);
        $this->assertSame(64, strlen($token));
        $this->assertSame('user@example.com', $this->resets->find($token, $now + 60));
    }

    public function testTheTableStoresOnlyTheTokenHash(): void
    {
        $token = $this->resets->create('user@example.com', 1_700_000_000);

        $stored = (string) $this->pdo->query('SELECT token_hash FROM panel_password_resets')->fetchColumn();
        $this->assertNotSame($token, $stored);
        $this->assertSame(hash('sha256', (string) $token), $stored);
    }

    public function testASecondRequestWithinTheResendIntervalGetsNoToken(): void
    {
        $now = 1_700_000_000;
        $this->resets->create('user@example.com', $now);

        $this->assertNull($this->resets->create('user@example.com', $now + PasswordResets::RESEND_INTERVAL_SECONDS - 1));
    }

    public function testARequestAfterTheResendIntervalReplacesTheToken(): void
    {
        $now = 1_700_000_000;
        $first = (string) $this->resets->create('user@example.com', $now);
        $later = $now + PasswordResets::RESEND_INTERVAL_SECONDS;

        $second = $this->resets->create('user@example.com', $later);
        $this->assertNotNull($second);
        $this->assertNull($this->resets->find($first, $later));
        $this->assertSame('user@example.com', $this->resets->find($second, $later));
    }

    public function testAnExpiredTokenIsNotFound(): void
    {
        $now = 1_700_000_000;
        $token = (string) $this->resets->create('user@example.com', $now);

        $this->assertNull($this->resets->find($token, $now + PasswordResets::EXPIRE_HOURS * 3600));
    }

    public function testAnUnknownTokenIsNotFound(): void
    {
        $this->assertNull($this->resets->find('nosuchtoken', 1_700_000_000));
    }

    public function testDeleteForEmailDropsThePendingToken(): void
    {
        $now = 1_700_000_000;
        $token = (string) $this->resets->create('user@example.com', $now);

        $this->resets->deleteForEmail('user@example.com');
        $this->assertNull($this->resets->find($token, $now + 60));
    }

    public function testCreateDropsTheExpiredRowsOfOtherMailboxes(): void
    {
        $now = 1_700_000_000;
        $this->resets->create('old@example.com', $now);

        $this->resets->create('new@example.com', $now + PasswordResets::EXPIRE_HOURS * 3600);
        $rows = $this->pdo->query('SELECT email FROM panel_password_resets')->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(['new@example.com'], $rows);
    }

    public function testEnsureTableExistsRunsTwice(): void
    {
        $this->resets->ensureTableExists();
        $this->resets->ensureTableExists();

        $this->assertNotNull($this->resets->create('user@example.com', 1_700_000_000));
    }
}
