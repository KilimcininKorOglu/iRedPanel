<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\MailingListRepositoryInterface;
use App\Repositories\RepositoryFactory;

/**
 * Keeps a mailing list account (SQL or LDAP) and its mlmmj spool in step.
 * Postfix hands list mail to mlmmj, which bounces it when the list directory
 * is missing, so every write goes to both sides.
 */
class MailingListService
{
    public static function create(string $address, string $domain, string $name, string $accessPolicy, int $maxMsgSize): void
    {
        $client = MlmmjadminClient::fromSettings();
        $repo = self::repo();

        $repo->createMailingList($address, $domain, $name, $accessPolicy, $maxMsgSize);
        try {
            $client->createList($address, MlmmjadminClient::listParams(
                $name, $accessPolicy, $maxMsgSize, self::owners($address, [])
            ));
        } catch (\Throwable $e) {
            $repo->deleteMailingList($address);
            throw $e;
        }
    }

    /**
     * Writes mlmmj first, so a failed API call leaves the account unchanged.
     */
    public static function update(string $address, string $name, string $accessPolicy, int $maxMsgSize, bool $active): void
    {
        $repo = self::repo();

        MlmmjadminClient::fromSettings()->updateList($address, MlmmjadminClient::listParams(
            $name, $accessPolicy, $maxMsgSize, self::owners($address, $repo->getOwners($address))
        ));
        $repo->updateMailingList($address, $name, $accessPolicy, $maxMsgSize, $active);
    }

    /**
     * @param string[] $owners
     */
    public static function setOwners(string $address, array $owners): void
    {
        MlmmjadminClient::fromSettings()->updateList($address, ['owner' => implode(',', self::owners($address, $owners))]);
        self::repo()->setOwners($address, $owners);
    }

    /**
     * Removes the list from mlmmj first, so a failed API call leaves the
     * account in place and the admin can retry.
     */
    public static function delete(string $address): void
    {
        MlmmjadminClient::fromSettings()->deleteList($address);
        self::repo()->deleteMailingList($address);
    }

    /**
     * @return string[]
     */
    public static function subscribers(string $address): array
    {
        return MlmmjadminClient::fromSettings()->subscribers($address);
    }

    /**
     * @param string[] $subscribers
     */
    public static function addSubscribers(string $address, array $subscribers): void
    {
        MlmmjadminClient::fromSettings()->addSubscribers($address, $subscribers);
    }

    /**
     * @param string[] $subscribers
     */
    public static function removeSubscribers(string $address, array $subscribers): void
    {
        MlmmjadminClient::fromSettings()->removeSubscribers($address, $subscribers);
    }

    /**
     * Splits one address per line, lowercased and without duplicates.
     *
     * @return string[]
     * @throws \InvalidArgumentException carrying the first invalid address
     */
    public static function parseAddresses(string $raw): array
    {
        $addresses = [];
        foreach (preg_split('/[\r\n,]+/', $raw) ?: [] as $line) {
            $address = strtolower(trim($line));
            if ($address === '') {
                continue;
            }
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                throw new \InvalidArgumentException($address);
            }
            $addresses[$address] = true;
        }

        return array_keys($addresses);
    }

    /**
     * mlmmj needs at least one owner; iRedMail uses postmaster of the domain.
     *
     * @param string[] $owners
     * @return string[]
     */
    private static function owners(string $address, array $owners): array
    {
        $owners = array_values(array_filter(array_map('trim', $owners), fn(string $o) => $o !== ''));
        if ($owners !== []) {
            return $owners;
        }

        return ['postmaster@' . (explode('@', $address, 2)[1] ?? '')];
    }

    private static function repo(): MailingListRepositoryInterface
    {
        return RepositoryFactory::getMailingListRepository();
    }
}
