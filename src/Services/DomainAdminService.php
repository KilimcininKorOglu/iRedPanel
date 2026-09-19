<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidInputException;
use App\Repositories\RepositoryFactory;

/**
 * Adds and removes the admins of a domain, for the domain page and the REST API. A global
 * admin adds an existing admin or a mailbox; as in iRedAdmin-Pro, a domain admin adds and
 * removes only the mailboxes of the domains that it manages. A mailbox that is added
 * becomes a domain admin and logs in with its own password.
 */
final class DomainAdminService
{
    /**
     * Validates every address first, then removes and adds.
     *
     * @param string[] $add
     * @param string[] $remove
     * @param bool $removeAll removes every current admin before the additions (global admin only)
     * @param list<string>|null $scope the managed domains of a domain admin or a domain key, null for a global admin
     * @param string $self the address of the admin who makes the change; it cannot remove itself
     * @throws InvalidInputException when an address may not be added or removed
     */
    public static function change(string $domain, array $add, array $remove, bool $removeAll, ?array $scope, string $self = ''): void
    {
        $add = self::normalized($add);
        $remove = self::normalized($remove);
        foreach ($add as $address) {
            self::assertAddable($address, $scope);
        }
        foreach ($remove as $address) {
            self::assertRemovable($address, $scope, strtolower($self));
        }

        $repo = RepositoryFactory::getAdminRepository();
        $current = $repo->getDomainAdmins($domain);
        foreach ($removeAll ? $current : array_intersect($remove, $current) as $address) {
            $repo->revokeDomainFromAdmin($address, $domain);
            ActivityLogger::logUpdate($domain, $address, "Domain admin removed: {$address}");
        }
        foreach ($add as $address) {
            $repo->assignDomainToAdmin($address, $domain);
            ActivityLogger::logUpdate($domain, $address, "Domain admin added: {$address}");
        }
    }

    /**
     * @param string[] $addresses
     * @return list<string> lowercased, without empty values and duplicates
     */
    private static function normalized(array $addresses): array
    {
        $addresses = array_map(static fn (string $address): string => strtolower(trim($address)), $addresses);

        return array_values(array_unique(array_filter($addresses, static fn (string $address): bool => $address !== '')));
    }

    /**
     * @param list<string>|null $scope
     * @throws InvalidInputException
     */
    private static function assertAddable(string $address, ?array $scope): void
    {
        self::assertEmail($address);
        if ($scope !== null) {
            self::assertMailboxInScope($address, $scope);
            return;
        }
        if (RepositoryFactory::getAdminRepository()->getAdmin($address) === null && !self::isMailbox($address)) {
            throw new InvalidInputException("No admin or mailbox exists with the address {$address}", 'domain.msg_admin_unknown', ['address' => $address]);
        }
    }

    /**
     * @param list<string>|null $scope
     * @throws InvalidInputException
     */
    private static function assertRemovable(string $address, ?array $scope, string $self): void
    {
        self::assertEmail($address);
        if ($scope === null) {
            return;
        }
        self::assertMailboxInScope($address, $scope);
        // A domain admin would lose the page that it is on.
        if ($address === $self) {
            throw new InvalidInputException('A domain admin cannot remove itself from the admins of the domain', 'domain.msg_admin_self');
        }
    }

    /**
     * @param list<string> $scope
     * @throws InvalidInputException
     */
    private static function assertMailboxInScope(string $address, array $scope): void
    {
        if (!in_array(explode('@', $address, 2)[1], $scope, true) || !self::isMailbox($address)) {
            throw new InvalidInputException(
                "A domain admin can add or remove only the mailboxes of its own domains: {$address}",
                'domain.msg_admin_not_allowed',
                ['address' => $address],
            );
        }
    }

    /**
     * @throws InvalidInputException
     */
    private static function assertEmail(string $address): void
    {
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidInputException("Invalid email address: {$address}", 'common.msg_invalid_email', ['address' => $address]);
        }
    }

    private static function isMailbox(string $address): bool
    {
        [$uid, $domain] = explode('@', $address, 2);

        return RepositoryFactory::getUserRepository()->getUser($domain, $uid) !== null;
    }
}
