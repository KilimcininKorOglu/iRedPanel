<?php

declare(strict_types=1);

namespace App\Repositories\Mysql;

use App\Models\DeletedMailbox;
use App\Models\PaginatedResult;
use App\Repositories\DeletedMailboxRepositoryInterface;

class MysqlDeletedMailboxRepository implements DeletedMailboxRepositoryInterface
{
    public function getPendingDeletions(int $page, int $perPage): PaginatedResult
    {
        $pdo = MysqlConnection::getInstance()->getPdo();
        $offset = ($page - 1) * $perPage;

        $countStmt = $pdo->query("SELECT COUNT(*) AS total FROM deleted_mailboxes");
        $totalCount = (int) $countStmt->fetch()['total'];

        $stmt = $pdo->prepare(
            "SELECT id, username, maildir, domain, admin, delete_date, timestamp
             FROM deleted_mailboxes
             ORDER BY timestamp DESC
             LIMIT :perPage OFFSET :offset"
        );
        $stmt->bindValue('perPage', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        while ($row = $stmt->fetch()) {
            $items[] = DeletedMailbox::fromMysqlRow($row);
        }

        return new PaginatedResult($items, $totalCount, $page, $perPage);
    }

    public function cancelDeletion(int $id): bool
    {
        $pdo = MysqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare("DELETE FROM deleted_mailboxes WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function reschedule(int $id, string $newDate): bool
    {
        $pdo = MysqlConnection::getInstance()->getPdo();

        $stmt = $pdo->prepare("UPDATE deleted_mailboxes SET delete_date = :newDate WHERE id = :id");
        $stmt->execute(['newDate' => $newDate, 'id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
