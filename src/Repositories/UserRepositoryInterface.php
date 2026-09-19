<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\MailboxStorage;
use App\Models\PaginatedResult;
use App\Models\User;

interface UserRepositoryInterface
{
    /**
     * Returns a User by uid within a domain, or null if not found.
     */
    public function getUser(string $domain, string $userId): ?User;

    /**
     * Returns all Users for a domain (excluding catch-all entries).
     *
     * @return User[]
     */
    public function getUsers(string $domain): array;

    /**
     * Updates a user's profile fields (everything except password).
     */
    public function updateUser(string $domain, User $user): void;

    /**
     * Updates a user's password with a pre-hashed value.
     */
    public function updateUserPassword(string $domain, string $userUid, string $passwordHash): void;

    /**
     * Creates a new user. Without $storage the mailbox gets the panel maildir layout and
     * the default mailbox format and folder.
     *
     * @throws \RuntimeException if the backend does not support user creation
     */
    public function createUser(string $domain, User $user, string $passwordHash, ?MailboxStorage $storage = null): void;

    /**
     * Whether a mailbox has one of the home directories (absolute paths).
     *
     * @param list<string> $paths
     */
    public function isMailboxPathInUse(array $paths): bool;

    /**
     * Whether this backend supports user creation.
     */
    public function supportsCreateUser(): bool;

    /**
     * Returns paginated user list for a domain.
     */
    public function getUsersPaginated(string $domain, int $page, int $perPage, ?string $startsWith = null, ?bool $activeOnly = null, string $sortBy = 'uid', string $sortDir = 'asc'): PaginatedResult;

    /**
     * Deletes a user and records the mailbox for deferred deletion.
     *
     * @param string|null $deleteDate the day (Y-m-d) the mailbox is removed from disk, null to keep it
     */
    public function deleteUser(string $domain, string $userUid, string $adminEmail, ?string $deleteDate = null): void;

    /**
     * Verifies a user's current password.
     */
    public function verifyUserPassword(string $domain, string $userUid, string $password): bool;

    /**
     * The Postfix transport of one mailbox, or null when the domain transport applies.
     */
    public function getTransport(string $domain, string $userUid): ?string;

    /**
     * Sets the Postfix transport of one mailbox; null restores the domain transport.
     */
    public function setTransport(string $domain, string $userUid, ?string $transport): void;

    /**
     * Renames a user email address, updating all related records.
     */
    public function renameUser(string $domain, string $oldUid, string $newUid): void;
}
