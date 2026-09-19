<?php

declare(strict_types=1);

namespace App\Api;

use App\Exceptions\InvalidInputException;
use App\Models\Alias;
use App\Models\MailingList;
use App\Repositories\RepositoryFactory;
use App\Services\MailingListService;
use App\Utils\AddressList;

class MailingListApiController
{
    public static function list(): void
    {
        ApiMiddleware::requireGlobalKey();
        $repo = RepositoryFactory::getMailingListRepository();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = (int) ($_GET['perPage'] ?? 50);
        $domain = $_GET['domain'] ?? null;

        try {
            $activeOnly = ApiInput::disabledOnly();
            $emailOnly = ApiInput::queryFlag('emailOnly');
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return;
        }

        $result = $repo->getMailingListsPaginated($page, $perPage, $domain, $activeOnly);
        ApiResponse::paginated($result, fn(MailingList $ml) => $emailOnly ? $ml->address : [
            'address' => $ml->address,
            'domain' => $ml->domain,
            'name' => $ml->name,
            'accessPolicy' => $ml->accessPolicy,
            'active' => $ml->active,
        ]);
    }

    public static function get(string $address): void
    {
        ApiMiddleware::requireGlobalKey();
        $repo = RepositoryFactory::getMailingListRepository();
        $ml = $repo->getMailingList($address);
        if ($ml === null) {
            ApiResponse::error('Mailing list not found', 404);
            return;
        }

        $owners = $repo->getOwners($address);
        ApiResponse::success(array_merge((array) $ml, ['owners' => $owners]));
    }

    public static function create(): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $data = ApiMiddleware::getJsonBody();
        $address = strtolower(trim((string) ($data['address'] ?? '')));
        $domain = strtolower(trim((string) ($data['domain'] ?? '')));

        $failure = AliasApiController::newAddressError($address, $domain);
        if ($failure !== null) {
            ApiResponse::error($failure[0], $failure[1]);
            return;
        }

        try {
            $accessPolicy = Alias::validAccessPolicy($data['accessPolicy'] ?? 'public');
            $maxMsgSize = MailingList::validMaxMsgSize($data['maxMsgSize'] ?? 0);
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return;
        }

        try {
            MailingListService::create(
                $address, $domain,
                $data['name'] ?? '',
                $accessPolicy,
                $maxMsgSize,
            );
        } catch (InvalidInputException $e) {
            ApiResponse::error($e->getMessage(), 403);
            return;
        }
        ApiResponse::created(['address' => $address]);
    }

    public static function update(string $address): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $repo = RepositoryFactory::getMailingListRepository();
        $ml = $repo->getMailingList($address);
        if ($ml === null) {
            ApiResponse::error('Mailing list not found', 404);
            return;
        }

        $data = ApiMiddleware::getJsonBody();
        $newsletter = array_key_exists('isNewsletter', $data) ? (bool) $data['isNewsletter'] : null;
        if ($newsletter !== null && !$repo->supportsNewsletter()) {
            ApiResponse::error('isNewsletter is not supported by this backend');
            return;
        }
        try {
            $accessPolicy = Alias::validAccessPolicy($data['accessPolicy'] ?? $ml->accessPolicy);
            $maxMsgSize = MailingList::validMaxMsgSize($data['maxMsgSize'] ?? $ml->maxMsgSize);
            $owners = self::ownersFromBody($data);
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return;
        }
        MailingListService::update(
            $address,
            $data['name'] ?? $ml->name,
            $accessPolicy,
            $maxMsgSize,
            (bool) ($data['active'] ?? $ml->active),
            $newsletter,
        );
        if ($owners !== null) {
            MailingListService::setOwners($address, $owners);
        }
        ApiResponse::success(['message' => 'Mailing list updated']);
    }

    /**
     * Reads `owners`, which GET returns. An empty array falls back to the default owner.
     *
     * @return string[]|null the owners, or null when the body does not set them
     */
    private static function ownersFromBody(array $data): ?array
    {
        if (!array_key_exists('owners', $data)) {
            return null;
        }
        $input = $data['owners'];
        if (!is_array($input) || array_filter($input, 'is_string') !== $input) {
            throw new \InvalidArgumentException('owners must be an array of email addresses');
        }

        try {
            return AddressList::parse(implode("\n", $input));
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException('Invalid owner: ' . $e->getMessage(), 0, $e);
        }
    }

    public static function delete(string $address): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $repo = RepositoryFactory::getMailingListRepository();
        if ($repo->getMailingList($address) === null) {
            ApiResponse::error('Mailing list not found', 404);
            return;
        }

        MailingListService::delete($address);
        ApiResponse::deleted();
    }

    public static function subscribers(string $address): void
    {
        ApiMiddleware::requireGlobalKey();
        if (self::listExists($address)) {
            ApiResponse::success(['subscribers' => MailingListService::subscribers($address)]);
        }
    }

    /**
     * Adds (POST) or removes (DELETE) the subscribers in `{"subscribers": [...]}`.
     */
    public static function changeSubscribers(string $address, bool $add): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $subscribers = self::listExists($address) ? self::addressesFromBody('subscribers', 'subscriber', false) : null;
        if ($subscribers === null) {
            return;
        }

        if ($add) {
            MailingListService::addSubscribers($address, $subscribers);
        } else {
            MailingListService::removeSubscribers($address, $subscribers);
        }
        ApiResponse::success(['subscribers' => MailingListService::subscribers($address)]);
    }

    public static function moderators(string $address): void
    {
        ApiMiddleware::requireGlobalKey();
        if (self::listExists($address)) {
            ApiResponse::success(['moderators' => MailingListService::moderators($address)]);
        }
    }

    /**
     * Replaces the moderators with `{"moderators": [...]}`. An empty array falls back
     * to the default moderator.
     */
    public static function setModerators(string $address): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $moderators = self::listExists($address) ? self::addressesFromBody('moderators', 'moderator', true) : null;
        if ($moderators === null) {
            return;
        }

        MailingListService::setModerators($address, $moderators);
        ApiResponse::success(['moderators' => MailingListService::moderators($address)]);
    }

    /**
     * Sends 404 when the list does not exist.
     */
    private static function listExists(string $address): bool
    {
        if (RepositoryFactory::getMailingListRepository()->getMailingList($address) !== null) {
            return true;
        }
        ApiResponse::error('Mailing list not found', 404);

        return false;
    }

    /**
     * Reads an array of email addresses from the JSON body, or sends 400.
     *
     * @return string[]|null the addresses, or null after the error response
     */
    private static function addressesFromBody(string $field, string $label, bool $allowEmpty): ?array
    {
        $input = ApiMiddleware::getJsonBody()[$field] ?? null;
        if (!is_array($input) || (!$allowEmpty && $input === []) || array_filter($input, 'is_string') !== $input) {
            ApiResponse::error($field . ' must be ' . ($allowEmpty ? 'an' : 'a non-empty') . ' array of email addresses');
            return null;
        }
        try {
            return AddressList::parse(implode("\n", $input));
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error('Invalid ' . $label . ': ' . $e->getMessage());
            return null;
        }
    }
}
