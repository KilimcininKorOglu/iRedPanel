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

        $owners = self::owners($address, []);
        $repo->createMailingList($address, $domain, $name, $accessPolicy, $maxMsgSize);
        try {
            $client->createList($address, MlmmjadminClient::listParams($name, $accessPolicy, $maxMsgSize, $owners));
        } catch (\Throwable $e) {
            $repo->deleteMailingList($address);
            throw $e;
        }
        // Record the default owner, so the owner list matches mlmmj.
        $repo->setOwners($address, $owners);
    }

    /**
     * Writes mlmmj first, so a failed API call leaves the account unchanged.
     *
     * @param ?bool $newsletter the public subscription flag; null keeps it unchanged
     */
    public static function update(string $address, string $name, string $accessPolicy, int $maxMsgSize, bool $active, ?bool $newsletter = null): void
    {
        $repo = self::repo();
        if ($newsletter !== null && !$repo->supportsNewsletter()) {
            throw new \DomainException('The backend does not support newsletters');
        }

        $params = MlmmjadminClient::listParams(
            $name, $accessPolicy, $maxMsgSize, self::owners($address, $repo->getOwners($address))
        );
        if ($newsletter !== null) {
            $params['enable_newsletter_subscription'] = $newsletter ? 'yes' : 'no';
        }

        MlmmjadminClient::fromSettings()->updateList($address, $params);
        $repo->updateMailingList($address, $name, $accessPolicy, $maxMsgSize, $active);
        if ($newsletter !== null) {
            $repo->setNewsletter($address, $newsletter);
        }
    }

    /**
     * An empty owner list becomes the default owner on both sides, so the stored
     * owners match mlmmj.
     *
     * @param string[] $owners
     */
    public static function setOwners(string $address, array $owners): void
    {
        $owners = self::owners($address, $owners);
        MlmmjadminClient::fromSettings()->updateList($address, ['owner' => implode(',', $owners)]);
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
     * Deletes every list of a domain through delete(). Domain deletion calls it first,
     * because the domain repository removes only the accounts and not the mlmmj spool.
     */
    public static function deleteDomainLists(string $domain): void
    {
        foreach (self::repo()->getMailingListsPaginated(1, PHP_INT_MAX, $domain)->items as $list) {
            self::delete($list->address);
        }
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
