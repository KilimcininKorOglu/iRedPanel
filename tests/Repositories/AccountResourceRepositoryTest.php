<?php

declare(strict_types=1);

namespace Tests\Repositories;

use App\Models\AccountResource;
use App\Models\ReplicatedAccount;
use App\Repositories\SqlAccountResourceRepository;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * Runs the shared account resource SQL against in-memory SQLite.
 */
#[RequiresPhpExtension('pdo_sqlite')]
class AccountResourceRepositoryTest extends TestCase
{
    private SqlAccountResourceRepository $repo;

    protected function setUp(): void
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $this->repo = new class ($pdo) extends SqlAccountResourceRepository {
            public function __construct(private readonly \PDO $connection) {}

            public function isAvailable(): bool
            {
                return true;
            }

            // SQLite has no session locks; the MySQL and PostgreSQL locks run only against a live server.
            public function tryLock(int $resourceId): bool
            {
                return true;
            }

            public function unlock(int $resourceId): void {}

            protected function pdo(): \PDO
            {
                return $this->connection;
            }

            protected function schema(): array
            {
                return [
                    'CREATE TABLE panel_account_resources (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT, domain TEXT,
                        host TEXT, port INTEGER, tls INTEGER, tls_verify INTEGER, timeout INTEGER, base_dn TEXT, bind_dn TEXT,
                        bind_password TEXT, user_filter TEXT, group_filter TEXT, interval_minutes INTEGER,
                        replicate_groups INTEGER, user_mail_attribute TEXT, user_attributes TEXT, group_mail_attribute TEXT,
                        group_name_attribute TEXT, group_access_policy TEXT, enabled INTEGER, last_run_at INTEGER,
                        last_status TEXT NOT NULL DEFAULT \'\')',
                    'CREATE TABLE panel_replicated_accounts (resource_id INTEGER, object_guid TEXT, kind TEXT, address TEXT,
                        source_dn TEXT, fingerprint TEXT, state TEXT, last_seen_at INTEGER, PRIMARY KEY (resource_id, object_guid))',
                    'CREATE TABLE panel_replication_runs (id INTEGER PRIMARY KEY AUTOINCREMENT, resource_id INTEGER,
                        started_at INTEGER, finished_at INTEGER, status TEXT, counts TEXT, message TEXT)',
                    'CREATE TABLE panel_replication_events (id INTEGER PRIMARY KEY AUTOINCREMENT, run_id INTEGER, action TEXT,
                        kind TEXT, address TEXT, detail TEXT)',
                ];
            }
        };
        $this->repo->ensureTablesExist();
    }

    public function testSaveInsertsThenUpdatesAResource(): void
    {
        $id = $this->repo->save(new AccountResource(domain: 'x.test', host: 'dc.x.test', bindPassword: 'v1:secret'));
        $resource = $this->repo->find($id);

        $this->assertSame('dc.x.test', $resource->host);
        $this->assertSame(AccountResource::DEFAULT_USER_ATTRIBUTES, $resource->userAttributes);
        $this->assertNull($resource->lastRunAt);

        $resource->host = 'dc2.x.test';
        $resource->userAttributes['title'] = '';
        $this->assertSame($id, $this->repo->save($resource));
        $this->assertSame('dc2.x.test', $this->repo->find($id)->host);
        $this->assertSame('', $this->repo->find($id)->userAttributes['title']);
        $this->assertCount(1, $this->repo->all());
    }

    public function testRecordRunSetsTheDueTime(): void
    {
        $id = $this->repo->save(new AccountResource(domain: 'x.test', intervalMinutes: 5));
        $this->assertTrue($this->repo->find($id)->isDue(1000));

        $this->repo->recordRun($id, 1000, AccountResource::STATUS_OK);
        $this->assertFalse($this->repo->find($id)->isDue(1299));
        $this->assertTrue($this->repo->find($id)->isDue(1300));

        $this->repo->setEnabled($id, false);
        $this->assertFalse($this->repo->find($id)->isDue(9999));
    }

    public function testSaveReplicatedAccountUpdatesTheLinkOfTheSameGuid(): void
    {
        $this->repo->saveReplicatedAccount(1, $this->link('g1', 'a@x.test', ReplicatedAccount::STATE_ACTIVE));
        $this->repo->saveReplicatedAccount(1, $this->link('g1', 'b@x.test', ReplicatedAccount::STATE_MISSING));
        $this->repo->saveReplicatedAccount(2, $this->link('g1', 'c@y.test', ReplicatedAccount::STATE_ACTIVE));

        $accounts = $this->repo->replicatedAccounts(1);
        $this->assertSame(['g1'], array_keys($accounts));
        $this->assertSame('b@x.test', $accounts['g1']->address);
        $this->assertSame(ReplicatedAccount::STATE_MISSING, $accounts['g1']->state);
    }

    public function testFindOwnerIgnoresConflictsAndSkips(): void
    {
        // A conflict link names an address that a local account owns, so the resource must not claim it.
        $this->repo->saveReplicatedAccount(1, $this->link('g1', 'local@x.test', ReplicatedAccount::STATE_CONFLICT));
        $this->repo->saveReplicatedAccount(1, $this->link('g2', 'ad@x.test', ReplicatedAccount::STATE_ACTIVE));

        $this->assertNull($this->repo->findOwner('local@x.test'));
        $this->assertSame('g2', $this->repo->findOwner('AD@x.test')->guid);
    }

    public function testDeleteRemovesTheResourceLinksAndLog(): void
    {
        $keep = $this->repo->save(new AccountResource(domain: 'keep.test'));
        $drop = $this->repo->save(new AccountResource(domain: 'drop.test'));
        foreach ([$keep, $drop] as $id) {
            $this->repo->saveReplicatedAccount($id, $this->link('g', "a@{$id}.test", ReplicatedAccount::STATE_ACTIVE));
            $this->repo->addEvent($this->repo->startRun($id, 1), 'create', 'user', "a@{$id}.test", '');
        }

        $this->repo->delete($drop);

        $this->assertNull($this->repo->find($drop));
        $this->assertSame([], $this->repo->replicatedAccounts($drop));
        $this->assertSame(0, $this->repo->countRuns($drop));
        $this->assertCount(1, $this->repo->replicatedAccounts($keep));
        $this->assertSame(1, $this->repo->countRuns($keep));
    }

    public function testRunsKeepTheirCountsAndPruneKeepsTheNewest(): void
    {
        $runIds = [];
        foreach ([1, 2, 3] as $at) {
            $runId = $this->repo->startRun(7, $at);
            $this->repo->addEvent($runId, 'update', 'user', 'a@x.test', '');
            $this->repo->finishRun($runId, $at + 1, AccountResource::STATUS_OK, ['update' => $at], '');
            $runIds[] = $runId;
        }

        $this->repo->pruneRuns(7, 2);

        $runs = $this->repo->runs(7, 10, 0);
        $this->assertSame([$runIds[2], $runIds[1]], array_map(intval(...), array_column($runs, 'id')));
        $this->assertSame(['update' => 3], $runs[0]['counts']);
        $this->assertSame([], $this->repo->events($runIds[0]));
        $this->assertCount(1, $this->repo->events($runIds[2]));
    }

    private function link(string $guid, string $address, string $state): ReplicatedAccount
    {
        return new ReplicatedAccount($guid, ReplicatedAccount::KIND_USER, $address, "CN={$guid}", 'f', $state, 1);
    }
}
