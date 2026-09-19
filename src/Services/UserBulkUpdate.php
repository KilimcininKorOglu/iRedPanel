<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\RepositoryFactory;
use App\Services\Replication\ReplicatedAccountGuard;
use App\Repositories\UserRepositoryInterface;
use App\Utils\PasswordUtils;

/**
 * One change applied to several mailboxes of a domain: account status, password,
 * language and Postfix transport. The caller validates the values and checks the
 * permissions.
 */
final class UserBulkUpdate
{
    /** The change fields that apply() accepts. */
    public const FIELDS = ['accountStatus', 'password', 'language', 'transport'];

    /**
     * Applies $changes to each mailbox and returns the addresses that were updated.
     * A mailbox that no longer exists is skipped.
     *
     * @param list<string> $uids local parts of the mailboxes
     * @param array<string, mixed> $changes one or more of FIELDS
     * @return list<string> the updated local parts
     */
    public static function apply(string $domain, array $uids, array $changes): array
    {
        $repo = RepositoryFactory::getUserRepository();
        $users = [];
        foreach ($uids as $uid) {
            $user = $repo->getUser($domain, $uid);
            if ($user === null) {
                continue;
            }
            // A status change of a replicated account belongs to the directory; refuse
            // before the first write, so the change is all or nothing.
            if (array_key_exists('accountStatus', $changes)) {
                ReplicatedAccountGuard::assertNotReplicated("{$uid}@{$domain}");
            }
            $users[] = $user;
        }

        foreach ($users as $user) {
            self::applyToUser($repo, $domain, $user, $changes);
        }

        return array_map(static fn (User $user): string => $user->uid, $users);
    }

    /**
     * @param array<string, mixed> $changes
     */
    private static function applyToUser(UserRepositoryInterface $repo, string $domain, User $user, array $changes): void
    {
        if (array_key_exists('accountStatus', $changes) || array_key_exists('language', $changes)) {
            $user->accountStatus = (bool) ($changes['accountStatus'] ?? $user->accountStatus);
            $user->language = array_key_exists('language', $changes) ? (string) $changes['language'] : $user->language;
            $repo->updateUser($domain, $user);
        }
        if (array_key_exists('password', $changes)) {
            // Each mailbox gets its own salt, as a single password change does.
            $repo->updateUserPassword($domain, $user->uid, PasswordUtils::generatePasswordHash((string) $changes['password']));
        }
        if (array_key_exists('transport', $changes)) {
            $repo->setTransport($domain, $user->uid, $changes['transport']);
        }
    }
}
