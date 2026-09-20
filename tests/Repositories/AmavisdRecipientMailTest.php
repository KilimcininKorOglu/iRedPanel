<?php

declare(strict_types=1);

namespace Tests\Repositories;

use App\Repositories\AmavisdRecipientMail;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * Runs the self-service quarantine SQL against an in-memory SQLite copy of the Amavisd
 * tables: a user sees and handles only the own copy of a message, and the message
 * leaves the quarantine when the last recipient has handled it.
 */
#[RequiresPhpExtension('pdo_sqlite')]
class AmavisdRecipientMailTest extends TestCase
{
    private \PDO $pdo;
    private AmavisdRecipientMail $mail;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        foreach ([
            'CREATE TABLE maddr (id INTEGER PRIMARY KEY, email TEXT)',
            "CREATE TABLE msgs (mail_id TEXT, secret_id TEXT, from_addr TEXT, subject TEXT, time_num INTEGER, spam_level REAL, content TEXT, quar_type TEXT)",
            "CREATE TABLE msgrcpt (mail_id TEXT, rid INTEGER, rs TEXT DEFAULT '')",
            'CREATE TABLE quarantine (mail_id TEXT, chunk_ind INTEGER, mail_text TEXT)',
            "INSERT INTO maddr VALUES (1, 'a@x.test'), (2, 'b@x.test'), (3, 'c@other.test')",
            "INSERT INTO msgs VALUES ('shared', 's1', 'spam@y.test', 'Both', 200, 9.1, 'S', 'Q'),
                                     ('only-a', 's2', 'spam@y.test', 'A only', 100, 8.0, 'S', 'Q'),
                                     ('clean', 's3', 'friend@y.test', 'Hello', 300, 0.1, 'C', '')",
            "INSERT INTO msgrcpt (mail_id, rid) VALUES ('shared', 1), ('shared', 2), ('shared', 3), ('only-a', 1), ('clean', 1)",
            "INSERT INTO quarantine VALUES ('shared', 1, 'x'), ('only-a', 1, 'y')",
        ] as $sql) {
            $this->pdo->exec($sql);
        }
        $this->mail = new AmavisdRecipientMail($this->pdo);
    }

    public function testUserSeesOwnQuarantineAndReceivedMail(): void
    {
        $this->assertSame(['shared', 'only-a'], array_column($this->mail->quarantined('a@x.test', 1, 20)->items, 'mail_id'));
        $this->assertSame(['shared'], array_column($this->mail->quarantined('b@x.test', 1, 20)->items, 'mail_id'));
        $this->assertSame(['clean', 'shared', 'only-a'], array_column($this->mail->received('a@x.test', 1, 20)->items, 'mail_id'));
    }

    public function testReleaseOfASharedMessageKeepsTheCopyOfTheOtherRecipient(): void
    {
        $released = [];
        $this->mail->release('shared', 'a@x.test', function (string $secretId) use (&$released): void {
            $released[] = $secretId;
        });

        $this->assertSame(['s1'], $released);
        $this->assertSame(['only-a'], array_column($this->mail->quarantined('a@x.test', 1, 20)->items, 'mail_id'));
        $this->assertSame(['shared'], array_column($this->mail->quarantined('b@x.test', 1, 20)->items, 'mail_id'));
        $this->assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM quarantine WHERE mail_id = 'shared'")->fetchColumn());

        $this->mail->delete('shared', 'b@x.test');
        $this->mail->delete('shared', 'c@other.test');
        $this->assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM quarantine WHERE mail_id = 'shared'")->fetchColumn());
    }

    public function testUserCannotHandleTheMailOfAnotherRecipient(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->mail->delete('only-a', 'b@x.test');
    }

    public function testReleasedMessageCannotBeReleasedAgain(): void
    {
        $this->mail->release('only-a', 'a@x.test', static function (): void {});

        $this->expectException(\RuntimeException::class);
        $this->mail->release('only-a', 'a@x.test', static function (): void {});
    }

    public function testAddressesOfAQuarantinedMessage(): void
    {
        $this->assertSame(
            ['sender' => 'spam@y.test', 'recipients' => ['a@x.test', 'b@x.test', 'c@other.test']],
            $this->mail->addresses('shared')
        );
    }

    public function testAddressesOfAHandledOrUnknownMessage(): void
    {
        $this->assertSame(['sender' => '', 'recipients' => []], $this->mail->addresses('clean'));
        $this->assertSame(['sender' => '', 'recipients' => []], $this->mail->addresses('no-such-id'));

        $this->mail->delete('only-a', 'a@x.test');
        $this->assertSame(['sender' => '', 'recipients' => []], $this->mail->addresses('only-a'));
    }

    public function testAddressesStripTheAngleBracketsOfTheSender(): void
    {
        $this->pdo->exec("UPDATE msgs SET from_addr = '<spam@y.test>' WHERE mail_id = 'shared'");

        $this->assertSame('spam@y.test', $this->mail->addresses('shared')['sender']);
    }

    /**
     * A domain admin handles the copies of its own domain only; a handled copy does not wait.
     */
    public function testPendingRecipientsOfADomain(): void
    {
        $this->assertSame(['a@x.test', 'b@x.test'], $this->mail->pendingRecipients('shared', 'x.test'));
        $this->assertSame(['c@other.test'], $this->mail->pendingRecipients('shared', 'other.test'));
        $this->assertSame([], $this->mail->pendingRecipients('clean', 'x.test'));

        $this->mail->delete('shared', 'a@x.test');
        $this->assertSame(['b@x.test'], $this->mail->pendingRecipients('shared', 'x.test'));
        // A domain name is not a pattern: another domain that ends with it does not match.
        $this->assertSame([], $this->mail->pendingRecipients('shared', 'her.test'));
    }
}
