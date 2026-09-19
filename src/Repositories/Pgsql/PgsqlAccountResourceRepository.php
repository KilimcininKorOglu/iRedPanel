<?php

declare(strict_types=1);

namespace App\Repositories\Pgsql;

use App\Repositories\SqlAccountResourceRepository;

class PgsqlAccountResourceRepository extends SqlAccountResourceRepository
{
    public function isAvailable(): bool
    {
        return IredadminPgsqlConnection::getInstance()->isAvailable();
    }

    protected function pdo(): \PDO
    {
        return IredadminPgsqlConnection::getInstance()->requirePdo();
    }

    protected function insertedId(string $table): int
    {
        return (int) $this->pdo()->lastInsertId("{$table}_id_seq");
    }

    protected function schema(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS panel_account_resources (
                id SERIAL PRIMARY KEY,
                type VARCHAR(20) NOT NULL,
                domain VARCHAR(255) NOT NULL,
                host VARCHAR(255) NOT NULL,
                port INT NOT NULL,
                tls SMALLINT NOT NULL DEFAULT 1,
                tls_verify SMALLINT NOT NULL DEFAULT 1,
                timeout INT NOT NULL DEFAULT 5,
                base_dn VARCHAR(1024) NOT NULL,
                bind_dn VARCHAR(1024) NOT NULL,
                bind_password TEXT NOT NULL,
                user_filter TEXT NOT NULL,
                group_filter TEXT NOT NULL,
                interval_minutes INT NOT NULL DEFAULT 5,
                replicate_groups SMALLINT NOT NULL DEFAULT 0,
                user_mail_attribute VARCHAR(255) NOT NULL,
                user_attributes TEXT NOT NULL,
                group_mail_attribute VARCHAR(255) NOT NULL,
                group_name_attribute VARCHAR(255) NOT NULL,
                group_access_policy VARCHAR(30) NOT NULL,
                enabled SMALLINT NOT NULL DEFAULT 1,
                last_run_at BIGINT DEFAULT NULL,
                last_status VARCHAR(20) NOT NULL DEFAULT ''
            )",
            "CREATE TABLE IF NOT EXISTS panel_replicated_accounts (
                resource_id INT NOT NULL,
                object_guid VARCHAR(64) NOT NULL,
                kind VARCHAR(10) NOT NULL,
                address VARCHAR(255) NOT NULL DEFAULT '',
                source_dn VARCHAR(1024) NOT NULL DEFAULT '',
                fingerprint VARCHAR(255) NOT NULL DEFAULT '',
                state VARCHAR(20) NOT NULL,
                last_seen_at BIGINT NOT NULL,
                PRIMARY KEY (resource_id, object_guid)
            )",
            "CREATE INDEX IF NOT EXISTS idx_panel_replicated_accounts_address ON panel_replicated_accounts (address)",
            "CREATE TABLE IF NOT EXISTS panel_replication_runs (
                id SERIAL PRIMARY KEY,
                resource_id INT NOT NULL,
                started_at BIGINT NOT NULL,
                finished_at BIGINT DEFAULT NULL,
                status VARCHAR(20) NOT NULL,
                counts TEXT NOT NULL,
                message TEXT NOT NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_panel_replication_runs_resource ON panel_replication_runs (resource_id)",
            "CREATE TABLE IF NOT EXISTS panel_replication_events (
                id SERIAL PRIMARY KEY,
                run_id INT NOT NULL,
                action VARCHAR(20) NOT NULL,
                kind VARCHAR(10) NOT NULL,
                address VARCHAR(255) NOT NULL DEFAULT '',
                detail TEXT NOT NULL
            )",
            "CREATE INDEX IF NOT EXISTS idx_panel_replication_events_run ON panel_replication_events (run_id)",
        ];
    }
}
