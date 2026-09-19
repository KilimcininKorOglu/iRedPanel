<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\PaginatedResult;

/**
 * The Amavisd mail of one recipient, as a self-service user sees it: the received mail
 * and the quarantined mail. A user releases or deletes a quarantined message for the own
 * address only; msgrcpt.rs records it ('R' released, 'D' deleted), and the message leaves
 * the quarantine when no recipient waits for it any more.
 */
final class AmavisdRecipientMail
{
    private const PENDING = "mr.rs NOT IN ('R', 'D')";

    /** @var \Closure(list<array<string, mixed>>): list<array<string, mixed>> */
    private readonly \Closure $rowsAsText;

    /**
     * @param string $bytes sprintf pattern for a parameter compared with a binary column:
     *        '%s' for MySQL varbinary, "convert_to(%s, 'UTF8')" for PostgreSQL bytea
     * @param string $text sprintf pattern that reads a binary column as text
     * @param ?\Closure $rowsAsText converts the binary values of fetched rows to strings
     */
    public function __construct(
        private readonly \PDO $pdo,
        private readonly string $bytes = '%s',
        private readonly string $text = '%s',
        ?\Closure $rowsAsText = null,
    ) {
        $this->rowsAsText = $rowsAsText ?? static fn (array $rows): array => $rows;
    }

    public function quarantined(string $email, int $page, int $perPage): PaginatedResult
    {
        $where = "{$this->recipient()} AND m.quar_type = 'Q' AND " . self::PENDING
            . ' AND EXISTS (SELECT 1 FROM quarantine q WHERE q.mail_id = m.mail_id)';

        return $this->page($where, $email, $page, $perPage);
    }

    public function received(string $email, int $page, int $perPage): PaginatedResult
    {
        return $this->page($this->recipient(), $email, $page, $perPage);
    }

    /**
     * @param \Closure(string): void $release asks Amavisd to release the message with the given secret_id
     * @throws \RuntimeException when the message is not in the quarantine of $email
     */
    public function release(string $mailId, string $email, \Closure $release): void
    {
        $release($this->pendingSecretId($mailId, $email));
        $this->finish($mailId, $email, 'R');
    }

    /**
     * @throws \RuntimeException when the message is not in the quarantine of $email
     */
    public function delete(string $mailId, string $email): void
    {
        $this->pendingSecretId($mailId, $email);
        $this->finish($mailId, $email, 'D');
    }

    private function recipient(): string
    {
        return 'a.email = ' . sprintf($this->bytes, ':email');
    }

    private function page(string $where, string $email, int $page, int $perPage): PaginatedResult
    {
        $from = "FROM msgs m JOIN msgrcpt mr ON m.mail_id = mr.mail_id JOIN maddr a ON a.id = mr.rid WHERE {$where}";
        $count = $this->pdo->prepare("SELECT COUNT(*) {$from}");
        $count->execute(['email' => $email]);

        $stmt = $this->pdo->prepare(
            "SELECT m.mail_id, m.from_addr, m.subject, m.time_num, m.spam_level, m.content, a.email AS recipient
             {$from} ORDER BY m.time_num DESC LIMIT :perPage OFFSET :offset"
        );
        $stmt->bindValue('email', $email);
        $stmt->bindValue('perPage', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();

        return new PaginatedResult(($this->rowsAsText)($stmt->fetchAll(\PDO::FETCH_ASSOC)), (int) $count->fetchColumn(), $page, $perPage);
    }

    private function pendingSecretId(string $mailId, string $email): string
    {
        $mail = sprintf($this->bytes, ':mailId');
        $stmt = $this->pdo->prepare(
            'SELECT ' . sprintf($this->text, 'm.secret_id') . " AS secret_id
             FROM msgs m JOIN msgrcpt mr ON m.mail_id = mr.mail_id JOIN maddr a ON a.id = mr.rid
             WHERE m.mail_id = {$mail} AND {$this->recipient()} AND " . self::PENDING . '
               AND EXISTS (SELECT 1 FROM quarantine q WHERE q.mail_id = m.mail_id)'
        );
        $stmt->execute(['mailId' => $mailId, 'email' => $email]);
        $secretId = $stmt->fetchColumn();
        if ($secretId === false) {
            throw new \RuntimeException("Quarantined message not found: {$mailId}");
        }

        return (string) $secretId;
    }

    /**
     * Records the action of the recipient and removes the quarantined copy when every
     * recipient released or deleted the message.
     */
    private function finish(string $mailId, string $email, string $status): void
    {
        $mail = sprintf($this->bytes, ':mailId');
        $address = sprintf($this->bytes, ':email');
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("UPDATE msgrcpt SET rs = :rs WHERE mail_id = {$mail} AND rid IN (SELECT id FROM maddr WHERE email = {$address})")
                ->execute(['rs' => $status, 'mailId' => $mailId, 'email' => $email]);
            $pending = $this->pdo->prepare("SELECT COUNT(*) FROM msgrcpt mr WHERE mr.mail_id = {$mail} AND " . self::PENDING);
            $pending->execute(['mailId' => $mailId]);
            if ((int) $pending->fetchColumn() === 0) {
                $this->pdo->prepare("DELETE FROM quarantine WHERE mail_id = {$mail}")->execute(['mailId' => $mailId]);
                $this->pdo->prepare("UPDATE msgs SET quar_type = '' WHERE mail_id = {$mail}")->execute(['mailId' => $mailId]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
