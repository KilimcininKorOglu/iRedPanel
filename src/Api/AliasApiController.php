<?php

declare(strict_types=1);

namespace App\Api;

use App\Models\Alias;
use App\Repositories\RepositoryFactory;

class AliasApiController
{
    public static function list(): void
    {
        ApiMiddleware::requireGlobalKey();
        $repo = RepositoryFactory::getAliasRepository();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = (int) ($_GET['perPage'] ?? 50);
        $domain = $_GET['domain'] ?? null;

        $result = $repo->getAliasesPaginated($page, $perPage, $domain);
        ApiResponse::paginated($result, fn(Alias $a) => [
            'address' => $a->address,
            'domain' => $a->domain,
            'name' => $a->name,
            'accessPolicy' => $a->accessPolicy,
            'active' => $a->active,
        ]);
    }

    public static function get(string $address): void
    {
        ApiMiddleware::requireGlobalKey();
        $repo = RepositoryFactory::getAliasRepository();
        $alias = $repo->getAlias($address);
        if ($alias === null) {
            ApiResponse::error('Alias not found', 404);
            return;
        }

        $members = $repo->getAliasMembers($address);
        $moderators = $repo->getModerators($address);

        ApiResponse::success([
            'address' => $alias->address,
            'domain' => $alias->domain,
            'name' => $alias->name,
            'accessPolicy' => $alias->accessPolicy,
            'active' => $alias->active,
            'members' => $members,
            'moderators' => $moderators,
        ]);
    }

    public static function create(): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $data = ApiMiddleware::getJsonBody();
        $address = $data['address'] ?? '';
        $domain = $data['domain'] ?? '';
        $name = $data['name'] ?? '';
        $members = $data['members'] ?? [];
        $accessPolicy = $data['accessPolicy'] ?? 'public';

        if ($address === '' || $domain === '') {
            ApiResponse::error('address and domain are required');
            return;
        }

        if (!str_ends_with(strtolower($address), '@' . strtolower($domain))) {
            ApiResponse::error('address must be in domain');
            return;
        }

        $repo = RepositoryFactory::getAliasRepository();
        if ($repo->isAddressInUse($address)) {
            ApiResponse::error('Address already in use', 409);
            return;
        }

        // Enforce domain alias limit
        $domainObj = RepositoryFactory::getDomainRepository()->getDomain($domain);
        if ($domainObj !== null && $domainObj->aliases > 0) {
            $aliasCount = $repo->countAliasesForDomain($domain);
            if ($aliasCount >= $domainObj->aliases) {
                ApiResponse::error("Domain alias limit reached ({$aliasCount}/{$domainObj->aliases})", 403);
                return;
            }
        }

        $repo->createAlias($address, $domain, $name, $members, $accessPolicy);
        ApiResponse::created(['address' => $address]);
    }

    public static function update(string $address): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $repo = RepositoryFactory::getAliasRepository();
        $alias = $repo->getAlias($address);
        if ($alias === null) {
            ApiResponse::error('Alias not found', 404);
            return;
        }

        $data = ApiMiddleware::getJsonBody();
        $repo->updateAlias(
            $address,
            $data['name'] ?? $alias->name,
            $data['members'] ?? $repo->getAliasMembers($address),
            $data['accessPolicy'] ?? $alias->accessPolicy,
            $data['active'] ?? $alias->active,
        );
        ApiResponse::success(['message' => 'Alias updated']);
    }

    public static function delete(string $address): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $repo = RepositoryFactory::getAliasRepository();
        if ($repo->getAlias($address) === null) {
            ApiResponse::error('Alias not found', 404);
            return;
        }

        $repo->deleteAlias($address);
        ApiResponse::deleted();
    }
}
