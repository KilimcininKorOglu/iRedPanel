<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidInputException;
use App\Repositories\RepositoryFactory;
use App\Repositories\WhiteBlacklistRepositoryInterface;
use App\Utils\AmavisdAddress;

/**
 * Adds and removes the white/blacklist entries of an account. The page, the
 * self-service page and the REST API share it, so all of them accept several
 * addresses in one request and refuse the same addresses.
 */
final class WhiteBlacklistService
{
    public const DIRECTIONS = ['inbound', 'outbound'];

    /**
     * @param string[] $senders
     * @return int the number of stored entries
     * @throws InvalidInputException when the account or one of the addresses is invalid
     */
    public static function add(string $account, array $senders, string $wb, string $direction): int
    {
        $senders = self::validated($account, $senders);
        $repo = self::repo();
        foreach ($senders as $sender) {
            if (self::isOutbound($direction)) {
                $repo->addOutboundEntry($account, $sender, $wb);
            } else {
                $repo->addInboundEntry($account, $sender, $wb);
            }
        }

        return count($senders);
    }

    /**
     * @param string[] $senders
     * @return int the number of removed entries
     */
    public static function remove(string $account, array $senders, string $direction): int
    {
        $repo = self::repo();
        $removed = 0;
        foreach (self::addresses($senders) as $sender) {
            $gone = self::isOutbound($direction)
                ? $repo->removeOutboundEntry($account, $sender)
                : $repo->removeInboundEntry($account, $sender);
            $removed += $gone ? 1 : 0;
        }

        return $removed;
    }

    /**
     * Removes every entry of one direction.
     *
     * @param ?string $wb W or B to remove only that kind, null for every entry
     * @return int the number of removed entries
     */
    public static function removeAll(string $account, string $direction, ?string $wb = null): int
    {
        $repo = self::repo();

        return self::isOutbound($direction)
            ? $repo->removeAllOutboundEntries($account, $wb)
            : $repo->removeAllInboundEntries($account, $wb);
    }

    /**
     * The white/blacklist kind of an input value. An unknown value is a whitelist entry.
     */
    public static function kind(mixed $value): string
    {
        return $value === 'B' ? 'B' : 'W';
    }

    /**
     * The kind to filter a list by, or null when every entry is wanted.
     *
     * @throws InvalidInputException when the value is neither W nor B
     */
    public static function filterKind(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === 'all') {
            return null;
        }
        if (!in_array($value, ['W', 'B'], true)) {
            throw new InvalidInputException('wb must be W or B', 'wblist.msg_invalid_kind');
        }

        return $value;
    }

    /**
     * Keeps the entries of one kind. An inbound row carries `sender`, an outbound
     * row `recipient`, so only `wb` is read here.
     *
     * @template TEntry of array{wb: string, ...}
     * @param array<int, TEntry> $entries
     * @return array<int, TEntry>
     */
    public static function filtered(array $entries, ?string $wb): array
    {
        if ($wb === null) {
            return $entries;
        }

        return array_values(array_filter($entries, static fn (array $entry): bool => $entry['wb'] === $wb));
    }

    /**
     * @param string[] $senders
     * @return list<string> the addresses that Amavisd can match
     * @throws InvalidInputException
     */
    private static function validated(string $account, array $senders): array
    {
        if (!AmavisdAddress::isValidAccount($account)) {
            throw new InvalidInputException("Invalid account: {$account}", 'common.msg_invalid_address', ['address' => $account]);
        }

        $addresses = self::addresses($senders);
        if ($addresses === []) {
            throw new InvalidInputException('sender is required', 'wblist.msg_sender_required');
        }
        foreach ($addresses as $sender) {
            if (!AmavisdAddress::isValidWblistAddress($sender)) {
                throw new InvalidInputException("Invalid sender: {$sender}", 'common.msg_invalid_address', ['address' => $sender]);
            }
        }

        return $addresses;
    }

    /**
     * @param string[] $senders
     * @return list<string> lowercased, without empty values and duplicates
     */
    private static function addresses(array $senders): array
    {
        $senders = array_map(static fn (string $sender): string => strtolower(trim($sender)), $senders);

        return array_values(array_unique(array_filter($senders, static fn (string $sender): bool => $sender !== '')));
    }

    private static function isOutbound(string $direction): bool
    {
        return $direction === 'outbound';
    }

    private static function repo(): WhiteBlacklistRepositoryInterface
    {
        return RepositoryFactory::getWhiteBlacklistRepository();
    }
}
