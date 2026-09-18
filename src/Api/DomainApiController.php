<?php

declare(strict_types=1);

namespace App\Api;

use App\Models\Domain;
use App\Models\Settings;
use App\Repositories\RepositoryFactory;
use App\Services\DomainOwnershipService;

class DomainApiController
{
    public static function list(): void
    {
        ApiMiddleware::requireGlobalKey();
        $repo = RepositoryFactory::getDomainRepository();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = (int) ($_GET['perPage'] ?? 50);

        $result = $repo->getDomainsPaginated($page, $perPage);
        ApiResponse::paginated($result, fn(Domain $d) => [
            'domainName' => $d->domainName,
            'description' => $d->description,
            'active' => $d->active,
            'mailboxes' => $d->mailboxes,
            'aliases' => $d->aliases,
            'quota' => $d->quota,
            'maxQuota' => $d->maxQuota,
            'created' => $d->created,
        ]);
    }

    public static function get(string $domain): void
    {
        ApiMiddleware::requireDomainAccess($domain);
        $d = RepositoryFactory::getDomainRepository()->getDomain($domain);
        if ($d === null) {
            ApiResponse::error('Domain not found', 404);
            return;
        }
        ApiResponse::success((array) $d);
    }

    public static function create(): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $data = ApiMiddleware::getJsonBody();
        $domain = Domain::fromFormData($data);

        if (empty($domain->domainName)) {
            ApiResponse::error('domainName is required');
            return;
        }

        $repo = RepositoryFactory::getDomainRepository();
        if ($repo->getDomain($domain->domainName) !== null) {
            ApiResponse::error('Domain already exists', 409);
            return;
        }
        if (RepositoryFactory::getDomainAliasRepository()->getAlias($domain->domainName) !== null) {
            ApiResponse::error('Domain already exists as an alias domain', 409);
            return;
        }

        if (Settings::getInstance()->requireDomainOwnershipVerification) {
            $ownershipRepo = RepositoryFactory::getDomainOwnershipRepository();
            if (!$ownershipRepo->isVerified($domain->domainName)) {
                $code = DomainOwnershipService::pendingCode($ownershipRepo, $domain->domainName, 'api');
                ApiResponse::error(
                    "Domain ownership must be verified before creation. Add a DNS TXT record with the value {$code} to {$domain->domainName}, then verify it on the Domain Ownership page.",
                    403,
                );
                return;
            }
        }

        $repo->createDomain($domain);
        ApiResponse::created(['domainName' => $domain->domainName]);
    }

    public static function update(string $domain): void
    {
        ApiMiddleware::requireDomainAccess($domain);
        ApiMiddleware::requireWriteAccess();
        $repo = RepositoryFactory::getDomainRepository();
        $existing = $repo->getDomain($domain);
        if ($existing === null) {
            ApiResponse::error('Domain not found', 404);
            return;
        }

        $data = ApiMiddleware::getJsonBody();
        $updated = new Domain(
            domainName: $domain,
            description: $data['description'] ?? $existing->description,
            active: $data['active'] ?? $existing->active,
            maxQuota: max(0, (int) ($data['maxQuota'] ?? $existing->maxQuota)),
            quota: max(0, (int) ($data['quota'] ?? $existing->quota)),
            mailboxes: max(0, (int) ($data['mailboxes'] ?? $existing->mailboxes)),
            aliases: max(0, (int) ($data['aliases'] ?? $existing->aliases)),
            transport: $data['transport'] ?? $existing->transport,
        );

        $repo->updateDomain($updated);
        ApiResponse::success(['message' => 'Domain updated']);
    }

    public static function delete(string $domain): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $repo = RepositoryFactory::getDomainRepository();
        if ($repo->getDomain($domain) === null) {
            ApiResponse::error('Domain not found', 404);
            return;
        }

        $repo->deleteDomain($domain, 'api');
        ApiResponse::deleted();
    }
}
