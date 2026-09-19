<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\PaginatedResult;

interface AmavisdRepositoryInterface extends AccountSettingsStoreInterface
{
    public function getQuarantinedMessages(int $page, int $perPage, ?string $domain = null): PaginatedResult;
    public function releaseMessage(string $mailId, string $requestedBy): void;
    public function deleteQuarantinedMessage(string $mailId): void;
    public function getMailLog(int $page, int $perPage, ?string $email = null): PaginatedResult;
    public function cleanupQuarantined(int $olderThanDays): int;
    public function cleanupMailLog(int $olderThanDays): int;

    /**
     * @param ?int $since Unix time; only messages received at or after it
     * @return string[] mail IDs of the quarantined messages, newest first
     */
    public function getQuarantinedMailIds(?int $since = null): array;

    /**
     * Quarantined messages of one recipient, newest first, at most $limit.
     *
     * @param int $since Unix time; only messages received after it
     * @return list<array{mail_id: string, subject: string, from_addr: string, spam_level: mixed, time_num: int}>
     */
    public function getQuarantinedForRecipient(string $email, int $since, int $limit = 100): array;

    /** The full message: Amavisd stores it in chunks, joined here in chunk order. */
    public function getQuarantinedMailText(string $mailId): string;

    /** Quarantined messages that wait for one recipient (self-service), newest first. */
    public function getQuarantinedForUser(string $email, int $page, int $perPage): PaginatedResult;

    /** Mail log rows of one recipient (self-service), newest first. */
    public function getReceivedMail(string $email, int $page, int $perPage): PaginatedResult;

    /**
     * Releases a quarantined message to one of its recipients only.
     *
     * @throws \RuntimeException when the message is not in the quarantine of $email or Amavisd refuses
     */
    public function releaseForRecipient(string $mailId, string $email): void;

    /**
     * Deletes a quarantined message for one of its recipients only.
     *
     * @throws \RuntimeException when the message is not in the quarantine of $email
     */
    public function deleteForRecipient(string $mailId, string $email): void;
}
