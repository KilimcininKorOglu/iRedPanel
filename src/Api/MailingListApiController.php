<?php

declare(strict_types=1);

namespace App\Api;

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

        $result = $repo->getMailingListsPaginated($page, $perPage, $domain);
        ApiResponse::paginated($result, fn(MailingList $ml) => [
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

        MailingListService::create(
            $address, $domain,
            $data['name'] ?? '',
            $accessPolicy,
            $maxMsgSize,
        );
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
        ApiResponse::success(['message' => 'Mailing list updated']);
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
        if (RepositoryFactory::getMailingListRepository()->getMailingList($address) === null) {
            ApiResponse::error('Mailing list not found', 404);
            return;
        }

        ApiResponse::success(['subscribers' => MailingListService::subscribers($address)]);
    }

    /**
     * Adds (POST) or removes (DELETE) the subscribers in `{"subscribers": [...]}`.
     */
    public static function changeSubscribers(string $address, bool $add): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        if (RepositoryFactory::getMailingListRepository()->getMailingList($address) === null) {
            ApiResponse::error('Mailing list not found', 404);
            return;
        }

        $input = ApiMiddleware::getJsonBody()['subscribers'] ?? null;
        if (!is_array($input) || $input === [] || array_filter($input, 'is_string') !== $input) {
            ApiResponse::error('subscribers must be a non-empty array of email addresses');
            return;
        }
        try {
            $subscribers = AddressList::parse(implode("\n", $input));
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error('Invalid subscriber: ' . $e->getMessage());
            return;
        }

        if ($add) {
            MailingListService::addSubscribers($address, $subscribers);
        } else {
            MailingListService::removeSubscribers($address, $subscribers);
        }
        ApiResponse::success(['subscribers' => MailingListService::subscribers($address)]);
    }
}
