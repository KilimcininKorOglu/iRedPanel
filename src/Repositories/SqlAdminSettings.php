<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Admin;

/**
 * Admin settings of the vmail database, for MySQL and PostgreSQL. iRedAdmin keeps the
 * settings of a standalone admin in admin.settings and of a mailbox admin in
 * mailbox.settings, both in the "key:value;" form.
 */
final class SqlAdminSettings
{
    /**
     * Writes the panel keys of the admin over its stored settings; the other keys stay.
     *
     * @throws \RuntimeException when the admin has no row
     */
    public static function write(\PDO $pdo, Admin $admin): void
    {
        $table = $admin->isMailboxAdmin ? 'mailbox' : 'admin';
        $select = $pdo->prepare("SELECT settings FROM {$table} WHERE username = :username");
        $select->execute(['username' => $admin->username]);
        $stored = $select->fetchColumn();
        if ($stored === false) {
            throw new \RuntimeException("Admin '{$admin->username}' not found");
        }

        $pdo->prepare("UPDATE {$table} SET settings = :settings WHERE username = :username")
            ->execute(['settings' => $admin->mergedSettings((string) $stored), 'username' => $admin->username]);
    }
}
