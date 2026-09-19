<?php

declare(strict_types=1);

namespace Tests\Models;

use App\Exceptions\InvalidInputException;
use App\Models\MailboxStorage;
use App\Repositories\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MailboxStorageTest extends TestCase
{
    public function testEmptyInputUsesTheDefaults(): void
    {
        $storage = MailboxStorage::fromInput(['mailboxFormat' => '', 'mailboxFolder' => ' '], true);

        $this->assertNull($storage->format);
        $this->assertNull($storage->folder);
        $this->assertNull($storage->path);
    }

    public function testValidInputIsNormalized(): void
    {
        $storage = MailboxStorage::fromInput([
            'mailboxFormat' => ' MDBOX ',
            'mailboxFolder' => 'Mail2',
            'maildir' => '/SRV/Mail/Store/Bob/',
        ], true);

        $this->assertSame('mdbox', $storage->format);
        $this->assertSame('Mail2', $storage->folder);
        $this->assertSame('/srv/mail/store/bob', $storage->path);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidInput(): array
    {
        return [
            'unknown format' => [['mailboxFormat' => 'mbox'], 'user.msg_invalid_mailbox_format'],
            'folder with a dot' => [['mailboxFolder' => '.Maildir'], 'user.msg_invalid_mailbox_folder'],
            'folder too long' => [['mailboxFolder' => str_repeat('a', 21)], 'user.msg_invalid_mailbox_folder'],
            'relative path' => [['maildir' => 'srv/mail/store/bob'], 'user.msg_invalid_maildir'],
            'three directories' => [['maildir' => '/srv/mail/bob'], 'user.msg_invalid_maildir'],
            'parent segment' => [['maildir' => '/srv/mail/../store/bob'], 'user.msg_invalid_maildir'],
            'space' => [['maildir' => '/srv/mail/store/b ob'], 'user.msg_invalid_maildir'],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidInput')]
    public function testInvalidInputIsRefused(array $data, string $translationKey): void
    {
        try {
            MailboxStorage::fromInput($data, true);
            $this->fail('the input must be refused');
        } catch (InvalidInputException $e) {
            $this->assertSame($translationKey, $e->translationKey);
        }
    }

    /**
     * A domain admin could otherwise point a new mailbox at the mail of another domain.
     */
    public function testOnlyAGlobalAdminSetsThePath(): void
    {
        $this->assertNull(MailboxStorage::fromInput(['maildir' => ''], false)->path);

        $this->expectException(InvalidInputException::class);
        MailboxStorage::fromInput(['maildir' => '/srv/mail/store/bob'], false);
    }

    public function testNonTextValueIsRefused(): void
    {
        $this->expectException(InvalidInputException::class);
        MailboxStorage::fromInput(['mailboxFormat' => ['mdbox']], true);
    }

    public function testDefaultLocationFollowsThePanelLayout(): void
    {
        $this->assertSame(
            ['/var/vmail', 'vmail1', 'e2e.test/bob/'],
            (new MailboxStorage())->location('e2e.test', 'bob', '/var/vmail/', 'vmail1'),
        );
    }

    public function testPathUnderTheStorageNodeKeepsTheNode(): void
    {
        $storage = new MailboxStorage(path: '/var/vmail/vmail1/e2e.test/custom/bob');

        $this->assertSame(
            ['/var/vmail', 'vmail1', 'e2e.test/custom/bob/'],
            $storage->location('e2e.test', 'bob', '/var/vmail', 'vmail1'),
        );
    }

    /**
     * Dovecot reads storagebasedirectory/storagenode/maildir, so any split gives the same path.
     */
    public function testOtherPathIsSplitIntoBaseNodeAndMaildir(): void
    {
        $storage = new MailboxStorage(path: '/srv/mail/store/bob');

        $this->assertSame(
            ['/srv', 'mail', 'store/bob/'],
            $storage->location('e2e.test', 'bob', '/var/vmail', 'vmail1'),
        );
    }

    public function testPathAndParentsListEveryDirectory(): void
    {
        $this->assertSame(
            ['/srv/mail/store/bob', '/srv/mail/store/bob/', '/srv/mail/store', '/srv/mail/store/', '/srv/mail', '/srv/mail/', '/srv', '/srv/'],
            (new MailboxStorage(path: '/srv/mail/store/bob'))->pathAndParents(),
        );
        $this->assertSame([], (new MailboxStorage())->pathAndParents());
    }

    public function testPathInUseIsRefused(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects($this->once())
            ->method('isMailboxPathInUse')
            ->with($this->callback(static fn(array $paths): bool => in_array('/srv/mail/', $paths, true)))
            ->willReturn(true);

        try {
            (new MailboxStorage(path: '/srv/mail/store/bob'))->assertPathFree($users);
            $this->fail('a path in use must be refused');
        } catch (InvalidInputException $e) {
            $this->assertSame('user.msg_maildir_in_use', $e->translationKey);
        }
    }

    public function testDefaultPathIsNotLookedUp(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects($this->never())->method('isMailboxPathInUse');

        (new MailboxStorage())->assertPathFree($users);
    }
}
