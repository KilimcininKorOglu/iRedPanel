<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\InvalidInputException;
use App\Utils\WholeNumber;

/**
 * How many days a deleted mailbox stays on disk before cli/deleteExpiredMailboxes.php
 * removes it, as in iRedAdmin (DAYS_TO_KEEP_REMOVED_MAILBOX). The value becomes the
 * delete_date of the deleted_mailboxes row; 0 keeps the mailbox forever.
 */
final class KeepMailboxDays
{
    /** The choices of a domain admin. */
    public const DOMAIN_ADMIN = [1, 7, 14, 21, 30, 60, 90, 180, 365];

    /** The choices of a global admin; 0 keeps the mailbox forever. */
    public const GLOBAL_ADMIN = [0, ...self::DOMAIN_ADMIN, 730, 1095];

    /**
     * @return list<int>
     */
    public static function options(bool $isGlobalAdmin): array
    {
        return $isGlobalAdmin ? self::GLOBAL_ADMIN : self::DOMAIN_ADMIN;
    }

    /**
     * @throws InvalidInputException when the value is not one of the choices of the admin
     */
    public static function parse(mixed $value, bool $isGlobalAdmin): int
    {
        $days = WholeNumber::parse(is_string($value) || is_int($value) ? $value : false);
        if ($days === null || !in_array($days, self::options($isGlobalAdmin), true)) {
            throw new InvalidInputException(
                'keepMailboxDays must be one of ' . implode(', ', self::options($isGlobalAdmin)),
                'common.msg_invalid_keep_days',
                ['days' => implode(', ', self::options($isGlobalAdmin))],
            );
        }

        return $days;
    }

    /**
     * The delete_date (Y-m-d) of a mailbox deleted today, or null to keep it forever.
     */
    public static function deleteDate(int $days, ?\DateTimeImmutable $today = null): ?string
    {
        if ($days === 0) {
            return null;
        }

        return ($today ?? new \DateTimeImmutable('today'))->modify("+{$days} days")->format('Y-m-d');
    }
}
