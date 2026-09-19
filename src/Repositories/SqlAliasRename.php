<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Moves a mail alias of the vmail database to a new address, for MySQL and PostgreSQL.
 * The members, the moderators and every reference to the alias follow the new address.
 */
final class SqlAliasRename
{
    /** Table and column pairs of the vmail schema that hold the address of an alias. */
    private const ADDRESS_COLUMNS = [
        ['alias', 'address'],
        ['forwardings', 'address'],
        ['forwardings', 'forwarding'],
        ['moderators', 'address'],
        ['moderators', 'moderator'],
        ['maillist_owners', 'owner'],
        ['sender_bcc_user', 'bcc_address'],
        ['recipient_bcc_user', 'bcc_address'],
        ['sender_bcc_domain', 'bcc_address'],
        ['recipient_bcc_domain', 'bcc_address'],
    ];

    public static function rename(\PDO $pdo, string $oldAddress, string $newAddress): void
    {
        $pdo->beginTransaction();
        try {
            foreach (self::ADDRESS_COLUMNS as [$table, $column]) {
                $pdo->prepare("UPDATE {$table} SET {$column} = :new WHERE {$column} = :old")
                    ->execute(['new' => $newAddress, 'old' => $oldAddress]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
