<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidInputException;
use App\Repositories\RepositoryFactory;

/**
 * The per-user alias addresses of a mailbox. Every message for such an address goes to
 * the mailbox, so the address must stay inside the domain of the mailbox and must not
 * belong to another account.
 */
final class UserAliasService
{
    /**
     * @throws InvalidInputException when the address is invalid, in another domain, or in use
     */
    public static function assertAvailable(string $domain, string $address): void
    {
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidInputException("Invalid alias address: {$address}", 'user.msg_alias_invalid', ['address' => $address]);
        }
        if (explode('@', $address, 2)[1] !== $domain) {
            throw new InvalidInputException("The alias must be in the domain {$domain}", 'user.msg_alias_domain_mismatch', ['domain' => $domain]);
        }
        if (RepositoryFactory::getAliasRepository()->isAddressInUse($address)) {
            throw new InvalidInputException("Address already in use: {$address}", 'common.msg_address_in_use', ['address' => $address]);
        }
    }

    /**
     * Adds one per-user alias.
     *
     * @throws InvalidInputException when the address is invalid, in another domain, or in use
     */
    public static function add(string $email, string $domain, string $address): void
    {
        self::assertAvailable($domain, $address);
        RepositoryFactory::getAliasRepository()->addUserAlias($email, $address);
    }

    /**
     * Replaces the per-user aliases with $wanted: adds the new addresses and removes the
     * stored ones that are missing. Nothing is written when one address is refused.
     *
     * @param list<string> $wanted lowercased addresses
     * @throws InvalidInputException when an address is invalid, in another domain, or in use
     */
    public static function setAliases(string $email, string $domain, array $wanted): void
    {
        $repo = RepositoryFactory::getAliasRepository();
        $current = $repo->getUserAliases($email);
        $added = array_diff($wanted, $current);
        foreach ($added as $address) {
            self::assertAvailable($domain, $address);
        }

        foreach (array_diff($current, $wanted) as $address) {
            $repo->removeUserAlias($email, $address);
        }
        foreach ($added as $address) {
            $repo->addUserAlias($email, $address);
        }
    }
}
