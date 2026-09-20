<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidInputException;
use App\Repositories\RepositoryFactory;

/**
 * A domain admin may not set a mailbox quota below the size the mailbox already
 * stores: Dovecot would refuse every new message without telling the user why.
 * A global admin may, because only a global admin can free the storage afterwards.
 */
final class MailboxQuotaFloor
{
    /**
     * Returns the refusal for a quota change, or null when the change is allowed.
     * An unlimited quota (0) and a quota that grows are always allowed, and a
     * backend that holds no used-quota row cannot refuse anything.
     */
    public static function error(string $email, int $newMb, bool $isGlobalAdmin): ?InvalidInputException
    {
        if ($isGlobalAdmin || $newMb === 0) {
            return null;
        }
        $usedBytes = RepositoryFactory::getQuotaRepository()->getUsedBytes($email);
        if ($usedBytes === null || $usedBytes <= 0) {
            return null;
        }
        $usedMb = (int) ceil($usedBytes / 1048576);
        if ($newMb >= $usedMb) {
            return null;
        }

        return new InvalidInputException(
            "mailQuota {$newMb} MB is below the stored size of {$usedMb} MB",
            'user.msg_quota_below_used',
            ['used' => (string) $usedMb, 'quota' => (string) $newMb],
        );
    }
}
