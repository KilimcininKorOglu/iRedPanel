<?php

declare(strict_types=1);

namespace Tests\Repositories;

use App\Repositories\SqlAliasRename;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * Runs the alias rename SQL against an in-memory SQLite copy of the vmail tables: the
 * alias, its members and moderators, and every reference to it move to the new address,
 * and the rows of other accounts stay unchanged.
 */
#[RequiresPhpExtension('pdo_sqlite')]
class SqlAliasRenameTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        foreach ([
            'CREATE TABLE alias (address TEXT, name TEXT)',
            'CREATE TABLE forwardings (address TEXT, forwarding TEXT)',
            'CREATE TABLE moderators (address TEXT, moderator TEXT)',
            'CREATE TABLE maillist_owners (address TEXT, owner TEXT)',
            'CREATE TABLE sender_bcc_user (username TEXT, bcc_address TEXT)',
            'CREATE TABLE recipient_bcc_user (username TEXT, bcc_address TEXT)',
            'CREATE TABLE sender_bcc_domain (domain TEXT, bcc_address TEXT)',
            'CREATE TABLE recipient_bcc_domain (domain TEXT, bcc_address TEXT)',
            "INSERT INTO alias VALUES ('team@x.test', 'Team'), ('other@x.test', 'Other')",
            "INSERT INTO forwardings VALUES ('team@x.test', 'a@x.test'), ('other@x.test', 'team@x.test'), ('other@x.test', 'b@x.test')",
            "INSERT INTO moderators VALUES ('team@x.test', 'a@x.test'), ('other@x.test', 'team@x.test')",
            "INSERT INTO maillist_owners VALUES ('list@x.test', 'team@x.test')",
            "INSERT INTO sender_bcc_user VALUES ('a@x.test', 'team@x.test')",
            "INSERT INTO recipient_bcc_user VALUES ('b@x.test', 'b@x.test')",
            "INSERT INTO sender_bcc_domain VALUES ('x.test', 'team@x.test')",
            "INSERT INTO recipient_bcc_domain VALUES ('x.test', 'team@x.test')",
        ] as $sql) {
            $this->pdo->exec($sql);
        }
    }

    public function testEveryReferenceMovesToTheNewAddress(): void
    {
        SqlAliasRename::rename($this->pdo, 'team@x.test', 'crew@x.test');

        $this->assertSame(['crew@x.test', 'other@x.test'], $this->column('SELECT address FROM alias ORDER BY address'));
        $this->assertSame(
            ['crew@x.test>a@x.test', 'other@x.test>b@x.test', 'other@x.test>crew@x.test'],
            $this->column("SELECT address || '>' || forwarding FROM forwardings ORDER BY 1"),
        );
        $this->assertSame(
            ['crew@x.test>a@x.test', 'other@x.test>crew@x.test'],
            $this->column("SELECT address || '>' || moderator FROM moderators ORDER BY 1"),
        );
        $this->assertSame(['crew@x.test'], $this->column('SELECT owner FROM maillist_owners'));
        foreach (['sender_bcc_user', 'sender_bcc_domain', 'recipient_bcc_domain'] as $table) {
            $this->assertSame(['crew@x.test'], $this->column("SELECT bcc_address FROM {$table}"), $table);
        }
        $this->assertSame(['b@x.test'], $this->column('SELECT bcc_address FROM recipient_bcc_user'));
    }

    public function testFailedUpdateLeavesEveryTableUnchanged(): void
    {
        $this->pdo->exec('DROP TABLE recipient_bcc_domain');

        $error = null;
        try {
            SqlAliasRename::rename($this->pdo, 'team@x.test', 'crew@x.test');
        } catch (\PDOException $e) {
            $error = $e;
        }

        $this->assertInstanceOf(\PDOException::class, $error, 'The rename must fail on the missing table');
        $this->assertSame(['other@x.test', 'team@x.test'], $this->column('SELECT address FROM alias ORDER BY address'));
        $this->assertSame(['team@x.test'], $this->column('SELECT bcc_address FROM sender_bcc_domain'));
        $this->assertFalse($this->pdo->inTransaction());
    }

    /**
     * @return list<string>
     */
    private function column(string $sql): array
    {
        return $this->pdo->query($sql)->fetchAll(\PDO::FETCH_COLUMN);
    }
}
