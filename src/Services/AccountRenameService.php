<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AddressConflictException;
use App\Exceptions\InvalidInputException;
use App\Repositories\RepositoryFactory;
use App\Services\Replication\ReplicatedAccountGuard;

/**
 * Renames a mailbox or a mail alias to a free address in the same domain, for the web
 * UI and the REST API. The Amavisd and iRedAPD settings of the account follow it.
 */
final class AccountRenameService
{
    /**
     * @return string the new address
     * @throws InvalidInputException for an invalid address
     * @throws AddressConflictException when the address is in use or the directory owns the mailbox
     */
    public static function renameUser(string $domain, string $oldUid, string $newUid): string
    {
        $oldEmail = "{$oldUid}@{$domain}";
        $newEmail = strtolower(trim($newUid)) . "@{$domain}";
        self::assertFree($oldEmail, $newEmail);

        RepositoryFactory::getUserRepository()->renameUser($domain, $oldUid, explode('@', $newEmail, 2)[0]);
        AccountSettingsService::renameAccount($oldEmail, $newEmail);
        ActivityLogger::logUpdate($domain, $newEmail, "Renamed user from {$oldEmail} to {$newEmail}");

        return $newEmail;
    }

    /**
     * @throws InvalidInputException for an invalid address or one in another domain
     * @throws AddressConflictException when the address is in use or the directory owns the alias
     */
    public static function renameAlias(string $oldAddress, string $newAddress): string
    {
        $newAddress = strtolower(trim($newAddress));
        $domain = explode('@', $oldAddress, 2)[1] ?? '';
        if ((explode('@', $newAddress, 2)[1] ?? '') !== $domain) {
            throw new InvalidInputException(
                "The new address must be in the domain {$domain}",
                'user.msg_alias_domain_mismatch',
                ['domain' => $domain],
            );
        }
        self::assertFree($oldAddress, $newAddress);

        RepositoryFactory::getAliasRepository()->renameAlias($oldAddress, $newAddress);
        AccountSettingsService::renameAccount($oldAddress, $newAddress);
        ActivityLogger::logUpdate($domain, $newAddress, "Renamed alias from {$oldAddress} to {$newAddress}");

        return $newAddress;
    }

    /**
     * @throws InvalidInputException|AddressConflictException
     */
    private static function assertFree(string $oldAddress, string $newAddress): void
    {
        if (filter_var($newAddress, FILTER_VALIDATE_EMAIL) === false || $newAddress === strtolower($oldAddress)) {
            throw new InvalidInputException("Invalid new address: {$newAddress}", 'common.msg_invalid_email', ['address' => $newAddress]);
        }
        if (RepositoryFactory::getAliasRepository()->isAddressInUse($newAddress)) {
            throw new AddressConflictException("Address already in use: {$newAddress}", 'common.msg_address_in_use', ['address' => $newAddress]);
        }
        // The directory owns the address; a rename here would be undone by the next replication.
        if (ReplicatedAccountGuard::isGroupLocked($oldAddress)) {
            throw new AddressConflictException("The directory manages {$oldAddress}", 'resource.msg_managed', ['address' => $oldAddress]);
        }
    }
}
