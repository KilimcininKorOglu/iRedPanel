<?php

declare(strict_types=1);

namespace Tests\Repositories;

use App\Models\Admin;
use App\Repositories\SqlAdminSettings;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * Runs the admin settings SQL against an in-memory SQLite copy of the vmail tables: a
 * standalone admin keeps its settings in admin.settings, a mailbox admin in
 * mailbox.settings, and the keys that the panel does not manage stay.
 */
#[RequiresPhpExtension('pdo_sqlite')]
class SqlAdminSettingsTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        foreach ([
            'CREATE TABLE admin (username TEXT, settings TEXT)',
            'CREATE TABLE mailbox (username TEXT, settings TEXT)',
            "INSERT INTO admin VALUES ('boss@x.test', '{\"create_max_users\":9}')",
            "INSERT INTO mailbox VALUES ('ann@a.test', 'disabled_mail_services:pop3;create_max_users:2;')",
        ] as $sql) {
            $this->pdo->exec($sql);
        }
    }

    public function testStandaloneAdminGetsTheIredadminForm(): void
    {
        SqlAdminSettings::write($this->pdo, new Admin(username: 'boss@x.test', createMaxDomains: 1, createNewDomains: true));

        $this->assertSame('create_max_domains:1;create_new_domains:yes;', $this->settings('admin', 'boss@x.test'));
    }

    public function testMailboxAdminWritesMailboxSettings(): void
    {
        SqlAdminSettings::write($this->pdo, new Admin(username: 'ann@a.test', isMailboxAdmin: true, createMaxLists: 3));

        $this->assertSame('disabled_mail_services:pop3;create_max_lists:3;', $this->settings('mailbox', 'ann@a.test'));
        $this->assertSame('{"create_max_users":9}', $this->settings('admin', 'boss@x.test'));
    }

    public function testMissingAdminIsReported(): void
    {
        $this->expectException(\RuntimeException::class);
        SqlAdminSettings::write($this->pdo, new Admin(username: 'nobody@x.test'));
    }

    private function settings(string $table, string $username): string
    {
        $stmt = $this->pdo->prepare("SELECT settings FROM {$table} WHERE username = :u");
        $stmt->execute(['u' => $username]);

        return (string) $stmt->fetchColumn();
    }
}
