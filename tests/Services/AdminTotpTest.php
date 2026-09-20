<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\AdminTotp;
use App\Services\RecoveryCodes;
use App\Utils\SecretBox;
use App\Utils\Totp;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * Runs the admin two-factor SQL against in-memory SQLite.
 */
#[RequiresPhpExtension('pdo_sqlite')]
#[RequiresPhpExtension('sodium')]
class AdminTotpTest extends TestCase
{
    private const ADMIN = 'admin@example.com';

    private \PDO $pdo;
    private AdminTotp $totp;
    private string $secret;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $this->totp = new AdminTotp($this->pdo, SecretBox::fromSecret('test-secret-key'));
        $this->secret = Totp::generateSecret();
    }

    public function testASetupIsPendingUntilItIsConfirmed(): void
    {
        $this->assertSame(AdminTotp::STATE_DISABLED, $this->totp->status(self::ADMIN)['state']);

        $this->totp->start(self::ADMIN, $this->secret, RecoveryCodes::generate(), 1_700_000_000);
        $this->assertSame(AdminTotp::STATE_PENDING, $this->totp->status(self::ADMIN)['state']);

        $this->totp->confirm(self::ADMIN);
        $this->assertSame(AdminTotp::STATE_ENABLED, $this->totp->status(self::ADMIN)['state']);
    }

    public function testTheSecretIsStoredEncryptedAndReadBack(): void
    {
        $this->totp->start(self::ADMIN, $this->secret, [], 1_700_000_000);

        $stored = (string) $this->pdo->query('SELECT secret FROM panel_admin_totp')->fetchColumn();
        $this->assertStringStartsWith('v1:', $stored);
        $this->assertStringNotContainsString($this->secret, $stored);
        $this->assertSame($this->secret, $this->totp->secret(self::ADMIN));
    }

    public function testVerifyTotpAcceptsOnlyTheCurrentCode(): void
    {
        $now = 1_700_000_000;
        $this->totp->start(self::ADMIN, $this->secret, [], $now);

        $this->assertTrue($this->totp->verifyTotp(self::ADMIN, Totp::code($this->secret, $now), $now));
        $this->assertFalse($this->totp->verifyTotp(self::ADMIN, '000000', $now - 600));
        $this->assertFalse($this->totp->verifyTotp('other@example.com', Totp::code($this->secret, $now), $now));
    }

    public function testARecoveryCodeWorksOnce(): void
    {
        $codes = RecoveryCodes::generate();
        $this->totp->start(self::ADMIN, $this->secret, $codes, 1_700_000_000);

        $this->assertSame(RecoveryCodes::COUNT, $this->totp->status(self::ADMIN)['remainingCodes']);
        $this->assertTrue($this->totp->consumeRecoveryCode(self::ADMIN, $codes[0]));
        $this->assertSame(RecoveryCodes::COUNT - 1, $this->totp->status(self::ADMIN)['remainingCodes']);
        $this->assertFalse($this->totp->consumeRecoveryCode(self::ADMIN, $codes[0]));
    }

    public function testARecoveryCodeIsReadInAnyShape(): void
    {
        $codes = RecoveryCodes::generate();
        $this->totp->start(self::ADMIN, $this->secret, $codes, 1_700_000_000);

        $this->assertTrue($this->totp->consumeRecoveryCode(self::ADMIN, strtoupper(str_replace('-', ' ', $codes[1]))));
    }

    public function testAnUnknownRecoveryCodeChangesNothing(): void
    {
        $this->totp->start(self::ADMIN, $this->secret, RecoveryCodes::generate(), 1_700_000_000);

        $this->assertFalse($this->totp->consumeRecoveryCode(self::ADMIN, 'dead-beef'));
        $this->assertSame(RecoveryCodes::COUNT, $this->totp->status(self::ADMIN)['remainingCodes']);
    }

    public function testReplaceRecoveryCodesDropsTheOldOnes(): void
    {
        $first = RecoveryCodes::generate();
        $this->totp->start(self::ADMIN, $this->secret, $first, 1_700_000_000);

        $second = RecoveryCodes::generate();
        $this->totp->replaceRecoveryCodes(self::ADMIN, $second);
        $this->assertFalse($this->totp->consumeRecoveryCode(self::ADMIN, $first[0]));
        $this->assertTrue($this->totp->consumeRecoveryCode(self::ADMIN, $second[0]));
    }

    public function testASecondSetupReplacesTheFirst(): void
    {
        $this->totp->start(self::ADMIN, $this->secret, [], 1_700_000_000);
        $this->totp->confirm(self::ADMIN);

        $other = Totp::generateSecret();
        $this->totp->start(self::ADMIN, $other, [], 1_700_000_100);
        $this->assertSame($other, $this->totp->secret(self::ADMIN));
        $this->assertSame(AdminTotp::STATE_PENDING, $this->totp->status(self::ADMIN)['state']);
        $this->assertSame(1, (int) $this->pdo->query('SELECT count(*) FROM panel_admin_totp')->fetchColumn());
    }

    public function testDeleteRemovesTheSetup(): void
    {
        $this->totp->start(self::ADMIN, $this->secret, RecoveryCodes::generate(), 1_700_000_000);
        $this->totp->confirm(self::ADMIN);

        $this->totp->delete(self::ADMIN);
        $this->assertSame(AdminTotp::STATE_DISABLED, $this->totp->status(self::ADMIN)['state']);
        $this->assertNull($this->totp->secret(self::ADMIN));
        $this->assertSame(0, $this->totp->status(self::ADMIN)['remainingCodes']);
    }

    public function testGenerateProducesDistinctCodes(): void
    {
        $codes = RecoveryCodes::generate();

        $this->assertCount(RecoveryCodes::COUNT, $codes);
        $this->assertSame($codes, array_unique($codes));
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{4}-[0-9a-f]{4}$/', $code);
        }
    }
}
