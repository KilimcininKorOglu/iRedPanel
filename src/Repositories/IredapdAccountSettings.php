<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Deletes and renames the iRedAPD rows of an account: throttle, greylisting
 * and the greylisting whitelists, together with their tracking rows.
 */
final class IredapdAccountSettings
{
    /** Tables whose `account` column holds the account address. */
    private const TABLES = ['throttle', 'greylisting', 'greylisting_whitelists', 'greylisting_whitelist_domain_spf'];

    public function __construct(private readonly \PDO $pdo) {}

    public function delete(AccountMatch $match): void
    {
        if ($match->isEmpty()) {
            return;
        }
        $this->transaction(fn() => $this->deleteRows($match));
    }

    public function deleteDomain(string $domain): void
    {
        $match = AccountMatch::domain($domain);
        $this->transaction(function () use ($match, $domain): void {
            $this->deleteRows($match);
            $this->pdo->prepare("DELETE FROM greylisting_tracking WHERE rcpt_domain = :domain")
                ->execute(['domain' => $domain]);
        });
    }

    public function rename(string $oldAccount, string $newAccount): void
    {
        $this->transaction(function () use ($oldAccount, $newAccount): void {
            $this->deleteRows(AccountMatch::accounts([$newAccount]));
            // The counters of the old address do not apply to the new one.
            $this->deleteTracking(AccountMatch::accounts([$oldAccount]));
            foreach (self::TABLES as $table) {
                $this->pdo->prepare("UPDATE {$table} SET account = :new WHERE account = :old")
                    ->execute(['new' => $newAccount, 'old' => $oldAccount]);
            }
        });
    }

    private function deleteRows(AccountMatch $match): void
    {
        $this->deleteTracking($match);
        [$where, $params] = $match->where('account');
        foreach (self::TABLES as $table) {
            $this->pdo->prepare("DELETE FROM {$table} WHERE {$where}")->execute($params);
        }
    }

    private function deleteTracking(AccountMatch $match): void
    {
        [$where, $params] = $match->where('account');
        $this->pdo->prepare("DELETE FROM throttle_tracking WHERE tid IN (SELECT id FROM throttle WHERE {$where})")
            ->execute($params);
        $this->pdo->prepare("DELETE FROM throttle_tracking WHERE {$where}")->execute($params);

        [$where, $params] = $match->where('recipient');
        $this->pdo->prepare("DELETE FROM greylisting_tracking WHERE {$where}")->execute($params);
    }

    private function transaction(callable $work): void
    {
        $this->pdo->beginTransaction();
        try {
            $work();
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
