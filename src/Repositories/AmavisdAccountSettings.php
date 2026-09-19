<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Deletes and renames the Amavisd rows of an account: its users row, the
 * white/blacklist entries that refer to that row, and the policy named after it.
 * The mailaddr rows stay, because they are external addresses shared by all accounts.
 */
final class AmavisdAccountSettings
{
    /**
     * @param string $emailPlaceholder sprintf pattern for users.email: '%s' for the
     *        MySQL varbinary column, "convert_to(%s, 'UTF8')" for the PostgreSQL bytea column
     */
    public function __construct(private readonly \PDO $pdo, private readonly string $emailPlaceholder = '%s') {}

    public function delete(AccountMatch $match): void
    {
        if ($match->isEmpty()) {
            return;
        }
        $this->transaction(fn() => $this->deleteRows($match));
    }

    public function rename(string $oldAccount, string $newAccount): void
    {
        $this->transaction(function () use ($oldAccount, $newAccount): void {
            $this->deleteRows(AccountMatch::accounts([$newAccount]));

            $params = ['new' => $newAccount, 'old' => $oldAccount];
            $email = fn(string $name): string => sprintf($this->emailPlaceholder, ":{$name}");
            $this->pdo->prepare("UPDATE users SET email = {$email('new')} WHERE email = {$email('old')}")
                ->execute($params);
            $this->pdo->prepare("UPDATE policy SET policy_name = :new WHERE policy_name = :old")
                ->execute($params);
        });
    }

    private function deleteRows(AccountMatch $match): void
    {
        [$usersWhere, $params] = $match->where('email', $this->emailPlaceholder);
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE {$usersWhere}");
        $stmt->execute($params);
        $ids = implode(', ', array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN)));

        if ($ids !== '') {
            // wblist.rid and outbound_wblist.sid are the local account (users.id).
            $this->pdo->exec("DELETE FROM wblist WHERE rid IN ({$ids})");
            $this->pdo->exec("DELETE FROM outbound_wblist WHERE sid IN ({$ids})");
            $this->pdo->exec("DELETE FROM users WHERE id IN ({$ids})");
        }

        [$policyWhere, $params] = $match->where('policy_name');
        $this->pdo->prepare("DELETE FROM policy WHERE {$policyWhere}")->execute($params);
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
