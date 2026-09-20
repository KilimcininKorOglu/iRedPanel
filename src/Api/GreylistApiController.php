<?php

declare(strict_types=1);

namespace App\Api;

use App\Repositories\IredapdRepositoryInterface;
use App\Repositories\RepositoryFactory;
use App\Utils\IredapdAccount;
use App\Utils\IredapdList;

class GreylistApiController
{
    /**
     * Every account that has an own greylisting setting.
     */
    public static function listAccounts(): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiResponse::success(['accounts' => self::repo()->getGreylistAccounts()]);
    }

    public static function get(string $account): void
    {
        ApiMiddleware::requireGlobalKey();
        if (!IredapdAccount::isValid($account)) {
            ApiResponse::error('Invalid account');
            return;
        }
        $repo = self::repo();

        ApiResponse::success([
            'account' => $account,
            'settings' => $repo->getGreylistSettings($account),
            'whitelistedSenders' => $repo->getWhitelistedSenders($account),
        ]);
    }

    /**
     * Sets the state and the whitelisted senders. The body either replaces the
     * senders with `whitelistedSenders` or changes them with `addSenders` and
     * `removeSenders`.
     */
    public static function update(string $account): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $data = ApiMiddleware::getJsonBody();
        if (!IredapdAccount::isValid($account)) {
            ApiResponse::error('Invalid account');
            return;
        }
        $repo = self::repo();

        try {
            $senders = ApiInput::listChange(
                $data,
                ['whitelistedSenders', 'addSenders', 'removeSenders'],
                $repo->getWhitelistedSenders($account),
                self::senders(...),
            );
            $enabled = array_key_exists('enabled', $data) ? ApiInput::bool($data, 'enabled', true) : null;
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return;
        }

        if ($enabled !== null) {
            $repo->setGreylistEnabled($account, $enabled);
        }
        if ($senders !== null) {
            $repo->setWhitelistedSenders($account, $senders);
        }

        ApiResponse::success(['message' => 'Greylist settings updated']);
    }

    /**
     * Removes the greylisting setting of the account and its whitelisted senders,
     * so iRedAPD applies the setting of the domain or of the global account.
     */
    public static function delete(string $account): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        if (!IredapdAccount::isValid($account)) {
            ApiResponse::error('Invalid account');
            return;
        }

        $removed = self::repo()->deleteGreylistSettings($account);
        if ($removed === 0) {
            ApiResponse::error('Greylist settings not found', 404);
            return;
        }

        ApiResponse::success(['message' => 'Greylist settings removed', 'count' => $removed]);
    }

    /**
     * The domains whose SPF records the iRedAPD job resolves into whitelisted senders.
     */
    public static function getDomains(): void
    {
        ApiMiddleware::requireGlobalKey();
        $repo = self::repo();

        ApiResponse::success([
            'domains' => $repo->getGreylistWhitelistDomains(),
            'spfSenders' => $repo->getGreylistSpfSenders(),
        ]);
    }

    /**
     * Replaces the domains with `domains`, or changes them with `addDomains`
     * and `removeDomains`.
     */
    public static function updateDomains(): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $data = ApiMiddleware::getJsonBody();
        $repo = self::repo();

        try {
            $domains = ApiInput::listChange(
                $data,
                ['domains', 'addDomains', 'removeDomains'],
                $repo->getGreylistWhitelistDomains(),
                self::domains(...),
            );
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return;
        }

        if ($domains === null) {
            ApiResponse::error('domains, addDomains or removeDomains is required');
            return;
        }

        $repo->setGreylistWhitelistDomains($domains);
        ApiResponse::success(['message' => 'Whitelisted domains updated', 'count' => count($domains)]);
    }

    /**
     * @return list<string>
     * @throws \InvalidArgumentException for an address that iRedAPD cannot match
     */
    private static function senders(mixed $input, string $field): array
    {
        $items = ApiInput::strings($input, $field);
        try {
            return IredapdList::greylistSenders($items);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException("Invalid {$field}: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    /**
     * @return list<string>
     * @throws \InvalidArgumentException for an invalid domain name
     */
    private static function domains(mixed $input, string $field): array
    {
        $items = ApiInput::strings($input, $field);
        try {
            return IredapdList::domains($items);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException("Invalid {$field}: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    private static function repo(): IredapdRepositoryInterface
    {
        return RepositoryFactory::getIredapdRepository();
    }
}
