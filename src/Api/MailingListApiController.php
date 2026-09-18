<?php

declare(strict_types=1);

namespace App\Api;

use App\Models\MailingList;
use App\Repositories\RepositoryFactory;

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
        $address = $data['address'] ?? '';
        $domain = $data['domain'] ?? '';

        if ($address === '' || $domain === '') {
            ApiResponse::error('address and domain are required');
            return;
        }

        $repo = RepositoryFactory::getMailingListRepository();
        if ($repo->getMailingList($address) !== null) {
            ApiResponse::error('Mailing list already exists', 409);
            return;
        }

        // Enforce domain alias limit (mailing lists count as aliases)
        $domainObj = RepositoryFactory::getDomainRepository()->getDomain($domain);
        if ($domainObj !== null && $domainObj->aliases > 0) {
            $aliasRepo = RepositoryFactory::getAliasRepository();
            $aliasCount = $aliasRepo->countAliasesForDomain($domain);
            if ($aliasCount >= $domainObj->aliases) {
                ApiResponse::error("Domain alias limit reached ({$aliasCount}/{$domainObj->aliases})", 403);
                return;
            }
        }

        $repo->createMailingList(
            $address, $domain,
            $data['name'] ?? '',
            $data['accessPolicy'] ?? 'public',
            (int) ($data['maxMsgSize'] ?? 0),
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
        $repo->updateMailingList(
            $address,
            $data['name'] ?? $ml->name,
            $data['accessPolicy'] ?? $ml->accessPolicy,
            (int) ($data['maxMsgSize'] ?? $ml->maxMsgSize),
            $data['active'] ?? $ml->active,
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

        $repo->deleteMailingList($address);
        ApiResponse::deleted();
    }
}
