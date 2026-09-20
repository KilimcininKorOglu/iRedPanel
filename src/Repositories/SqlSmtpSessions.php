<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\PaginatedResult;

/**
 * The SMTP sessions that iRedAPD recorded in the iredapd table `smtp_sessions`,
 * for MySQL and PostgreSQL. The SQL is the same on both backends, so both
 * repositories share this class.
 *
 * The table belongs to iRedAPD: its own cleanup job removes the old rows, so the
 * panel only reads here.
 */
final class SqlSmtpSessions
{
    /** The columns of one row, in the order the detail page shows them. */
    private const COLUMNS = 'id, time, time_num, action, reason, instance,
             client_address, client_name, reverse_client_name, helo_name,
             sender, sender_domain, sasl_username, sasl_domain,
             recipient, recipient_domain,
             encryption_protocol, encryption_cipher, server_address, server_port';

    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * One page of sessions, newest first. Every filter matches an indexed column
     * exactly, so a busy table stays fast.
     *
     * @param ?string $action one of the values that actions() returns
     * @param ?string $email matches the sender, the recipient or the SASL user name
     * @param ?string $clientAddress the IP address of the SMTP client
     */
    public function paginated(
        int $page,
        int $perPage,
        ?string $action = null,
        ?string $email = null,
        ?string $clientAddress = null
    ): PaginatedResult {
        [$where, $params] = $this->filter($action, $email, $clientAddress);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) AS total FROM smtp_sessions {$where}");
        $countStmt->execute($params);
        $totalCount = (int) $countStmt->fetch()['total'];

        $stmt = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . " FROM smtp_sessions {$where} ORDER BY id DESC LIMIT :perPage OFFSET :offset"
        );
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->bindValue('perPage', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue('offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();

        return new PaginatedResult($stmt->fetchAll(), $totalCount, $page, $perPage);
    }

    /**
     * One session, or null when no row carries the id.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM smtp_sessions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * The actions that the stored rows carry, for the filter list.
     *
     * @return list<string>
     */
    public function actions(): array
    {
        return $this->pdo->query("SELECT DISTINCT action FROM smtp_sessions WHERE action <> '' ORDER BY action")
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Whether the table exists. iRedAPD creates it and writes it only while
     * `LOG_SMTP_SESSIONS` is on, so an empty page needs this answer to explain itself.
     */
    public function isAvailable(): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM smtp_sessions LIMIT 1');
        } catch (\PDOException) {
            return false;
        }

        return true;
    }

    /**
     * @return array{0: string, 1: array<string, string>} the WHERE clause and its parameters
     */
    private function filter(?string $action, ?string $email, ?string $clientAddress): array
    {
        $conditions = [];
        $params = [];

        if ((string) $action !== '') {
            $conditions[] = 'action = :action';
            $params['action'] = (string) $action;
        }
        if ((string) $email !== '') {
            // MySQL refuses a named parameter that one statement uses more than once,
            // so each of the three columns gets its own name.
            $conditions[] = '(sender = :emailSender OR recipient = :emailRecipient OR sasl_username = :emailSasl)';
            $address = strtolower((string) $email);
            $params['emailSender'] = $address;
            $params['emailRecipient'] = $address;
            $params['emailSasl'] = $address;
        }
        if ((string) $clientAddress !== '') {
            $conditions[] = 'client_address = :clientAddress';
            $params['clientAddress'] = (string) $clientAddress;
        }

        return [$conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions), $params];
    }
}
