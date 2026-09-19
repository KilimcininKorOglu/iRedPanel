<?php

declare(strict_types=1);

namespace Tests\Repositories;

use App\Repositories\SqlDomainAdmins;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * Runs the domain admin SQL against an in-memory SQLite copy of the vmail tables: a
 * mailbox keeps the domain admin flag while it administers a domain, and a global
 * admin row does not count as a domain.
 */
#[RequiresPhpExtension('pdo_sqlite')]
class SqlDomainAdminsTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        foreach ([
            'CREATE TABLE mailbox (username TEXT, isadmin INTEGER DEFAULT 0)',
            'CREATE TABLE domain_admins (username TEXT, domain TEXT)',
            "INSERT INTO mailbox (username) VALUES ('ann@a.test'), ('bob@a.test')",
            "INSERT INTO domain_admins VALUES ('ann@a.test', 'a.test'), ('ann@a.test', 'b.test'), ('Boss@x.test', 'a.test'), ('bob@a.test', 'ALL')",
        ] as $sql) {
            $this->pdo->exec($sql);
        }
    }

    public function testListsTheAdminsOfOneDomainLowercased(): void
    {
        $this->assertSame(['ann@a.test', 'boss@x.test'], SqlDomainAdmins::list($this->pdo, 'a.test'));
        $this->assertSame(['ann@a.test'], SqlDomainAdmins::list($this->pdo, 'b.test'));
        $this->assertSame([], SqlDomainAdmins::list($this->pdo, 'c.test'));
    }

    public function testMailboxKeepsTheFlagWhileItAdministersADomain(): void
    {
        SqlDomainAdmins::markMailbox($this->pdo, 'ann@a.test');
        $this->pdo->exec("DELETE FROM domain_admins WHERE username = 'ann@a.test' AND domain = 'a.test'");
        SqlDomainAdmins::unmarkMailboxWithoutDomains($this->pdo, 'ann@a.test');
        $this->assertSame(1, $this->isAdmin('ann@a.test'), 'another row still names ann@a.test');
    }

    public function testMailboxLosesTheFlagWithItsLastDomain(): void
    {
        SqlDomainAdmins::markMailbox($this->pdo, 'bob@a.test');
        SqlDomainAdmins::unmarkMailboxWithoutDomains($this->pdo, 'bob@a.test');
        $this->assertSame(0, $this->isAdmin('bob@a.test'), 'the ALL row of a global admin is no domain');
    }

    private function isAdmin(string $username): int
    {
        $stmt = $this->pdo->prepare('SELECT isadmin FROM mailbox WHERE username = :u');
        $stmt->execute(['u' => $username]);

        return (int) $stmt->fetchColumn();
    }
}
