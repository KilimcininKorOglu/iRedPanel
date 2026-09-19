<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AccountResource;
use App\Models\ReplicatedAccount;

/**
 * The account resource SQL shared by MySQL and PostgreSQL. Only the connection,
 * the table definitions and the generated id differ per database.
 * Times are Unix timestamps and flags are 0 or 1, so the SQL stays portable.
 */
abstract class SqlAccountResourceRepository implements AccountResourceRepositoryInterface
{
    abstract protected function pdo(): \PDO;

    /** @return string[] CREATE TABLE and CREATE INDEX statements */
    abstract protected function schema(): array;

    protected function insertedId(string $table): int
    {
        return (int) $this->pdo()->lastInsertId();
    }

    public function ensureTablesExist(): void
    {
        foreach ($this->schema() as $statement) {
            $this->pdo()->exec($statement);
        }
    }

    public function all(): array
    {
        $rows = $this->pdo()->query('SELECT * FROM panel_account_resources ORDER BY domain, id')->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(AccountResource::fromRow(...), $rows);
    }

    public function find(int $id): ?AccountResource
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM panel_account_resources WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : AccountResource::fromRow($row);
    }

    public function save(AccountResource $resource): int
    {
        $row = $resource->toRow();
        if ($resource->id === 0) {
            $columns = implode(', ', array_keys($row));
            $values = ':' . implode(', :', array_keys($row));
            $this->pdo()->prepare("INSERT INTO panel_account_resources ({$columns}) VALUES ({$values})")->execute($row);

            return $this->insertedId('panel_account_resources');
        }

        $assignments = implode(', ', array_map(static fn(string $c): string => "{$c} = :{$c}", array_keys($row)));
        $this->pdo()->prepare("UPDATE panel_account_resources SET {$assignments} WHERE id = :id")
            ->execute($row + ['id' => $resource->id]);

        return $resource->id;
    }

    public function delete(int $id): void
    {
        $this->transaction(function () use ($id): void {
            $params = ['id' => $id];
            $this->pdo()->prepare(
                'DELETE FROM panel_replication_events WHERE run_id IN (SELECT id FROM panel_replication_runs WHERE resource_id = :id)'
            )->execute($params);
            $this->pdo()->prepare('DELETE FROM panel_replication_runs WHERE resource_id = :id')->execute($params);
            $this->pdo()->prepare('DELETE FROM panel_replicated_accounts WHERE resource_id = :id')->execute($params);
            $this->pdo()->prepare('DELETE FROM panel_account_resources WHERE id = :id')->execute($params);
        });
    }

    public function setEnabled(int $id, bool $enabled): void
    {
        $this->pdo()->prepare('UPDATE panel_account_resources SET enabled = :enabled WHERE id = :id')
            ->execute(['enabled' => (int) $enabled, 'id' => $id]);
    }

    public function recordRun(int $id, int $at, string $status): void
    {
        $this->pdo()->prepare('UPDATE panel_account_resources SET last_run_at = :at, last_status = :status WHERE id = :id')
            ->execute(['at' => $at, 'status' => $status, 'id' => $id]);
    }

    public function replicatedAccounts(int $resourceId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM panel_replicated_accounts WHERE resource_id = :id');
        $stmt->execute(['id' => $resourceId]);
        $accounts = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $accounts[$row['object_guid']] = ReplicatedAccount::fromRow($row);
        }

        return $accounts;
    }

    public function saveReplicatedAccount(int $resourceId, ReplicatedAccount $account): void
    {
        $params = [
            'resource' => $resourceId,
            'guid' => $account->guid,
            'kind' => $account->kind,
            'address' => $account->address,
            'dn' => $account->sourceDn,
            'fingerprint' => $account->fingerprint,
            'state' => $account->state,
            'seen' => $account->lastSeenAt,
        ];
        $update = $this->pdo()->prepare(
            'UPDATE panel_replicated_accounts
             SET kind = :kind, address = :address, source_dn = :dn, fingerprint = :fingerprint,
                 state = :state, last_seen_at = :seen
             WHERE resource_id = :resource AND object_guid = :guid'
        );
        $update->execute($params);
        if ($update->rowCount() > 0) {
            return;
        }
        $this->pdo()->prepare(
            'INSERT INTO panel_replicated_accounts
                 (resource_id, object_guid, kind, address, source_dn, fingerprint, state, last_seen_at)
             VALUES (:resource, :guid, :kind, :address, :dn, :fingerprint, :state, :seen)'
        )->execute($params);
    }

    public function deleteReplicatedAccount(int $resourceId, string $guid): void
    {
        $this->pdo()->prepare('DELETE FROM panel_replicated_accounts WHERE resource_id = :resource AND object_guid = :guid')
            ->execute(['resource' => $resourceId, 'guid' => $guid]);
    }

    public function findOwner(string $address): ?ReplicatedAccount
    {
        $stmt = $this->pdo()->prepare(
            "SELECT * FROM panel_replicated_accounts
             WHERE address = :address AND state IN ('active', 'missing')
             ORDER BY resource_id LIMIT 1"
        );
        $stmt->execute(['address' => strtolower($address)]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : ReplicatedAccount::fromRow($row);
    }

    public function startRun(int $resourceId, int $at): int
    {
        $this->pdo()->prepare(
            "INSERT INTO panel_replication_runs (resource_id, started_at, finished_at, status, counts, message)
             VALUES (:resource, :at, NULL, 'running', '{}', '')"
        )->execute(['resource' => $resourceId, 'at' => $at]);

        return $this->insertedId('panel_replication_runs');
    }

    public function finishRun(int $runId, int $at, string $status, array $counts, string $message): void
    {
        $this->pdo()->prepare(
            'UPDATE panel_replication_runs SET finished_at = :at, status = :status, counts = :counts, message = :message
             WHERE id = :id'
        )->execute([
            'at' => $at,
            'status' => $status,
            'counts' => json_encode((object) $counts, JSON_THROW_ON_ERROR),
            'message' => $message,
            'id' => $runId,
        ]);
    }

    public function addEvent(int $runId, string $action, string $kind, string $address, string $detail): void
    {
        $this->pdo()->prepare(
            'INSERT INTO panel_replication_events (run_id, action, kind, address, detail)
             VALUES (:run, :action, :kind, :address, :detail)'
        )->execute(['run' => $runId, 'action' => $action, 'kind' => $kind, 'address' => $address, 'detail' => $detail]);
    }

    public function pruneRuns(int $resourceId, int $keep): void
    {
        $stmt = $this->pdo()->prepare('SELECT id FROM panel_replication_runs WHERE resource_id = :id ORDER BY id DESC');
        $stmt->execute(['id' => $resourceId]);
        $old = array_slice(array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN)), $keep);
        if ($old === []) {
            return;
        }
        $ids = implode(', ', $old);
        $this->transaction(function () use ($ids): void {
            $this->pdo()->exec("DELETE FROM panel_replication_events WHERE run_id IN ({$ids})");
            $this->pdo()->exec("DELETE FROM panel_replication_runs WHERE id IN ({$ids})");
        });
    }

    public function runs(int $resourceId, int $limit, int $offset): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM panel_replication_runs WHERE resource_id = :id ORDER BY id DESC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue('id', $resourceId, \PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function (array $row): array {
            $row['counts'] = json_decode((string) $row['counts'], true, flags: JSON_THROW_ON_ERROR);
            return $row;
        }, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function countRuns(int $resourceId): int
    {
        $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM panel_replication_runs WHERE resource_id = :id');
        $stmt->execute(['id' => $resourceId]);

        return (int) $stmt->fetchColumn();
    }

    public function events(int $runId): array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM panel_replication_events WHERE run_id = :id ORDER BY id');
        $stmt->execute(['id' => $runId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function transaction(callable $work): void
    {
        $this->pdo()->beginTransaction();
        try {
            $work();
            $this->pdo()->commit();
        } catch (\Throwable $e) {
            $this->pdo()->rollBack();
            throw $e;
        }
    }
}
