<?php

declare(strict_types=1);

namespace App\Api;

use App\Models\Domain;
use App\Models\Settings;
use App\Repositories\RepositoryFactory;
use App\Services\AccountSettingsService;
use App\Services\DomainOwnershipService;
use App\Services\MailingListService;

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
        try {
            // A new domain starts active unless the request sets active, as in the web form.
            $domain = Domain::fromFormData($data + ['active' => true]);
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return;
        }

        if (empty($domain->domainName)) {
            ApiResponse::error('domainName is required');
            return;
        }
        if (!Domain::isValidName($domain->domainName)) {
            ApiResponse::error('domainName must be a valid domain name');
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
        // The limits and the status bound the domain admin, so only a global key changes
        // them, as only a global admin edits a domain in the web panel.
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $repo = RepositoryFactory::getDomainRepository();
        $existing = $repo->getDomain($domain);
        if ($existing === null) {
            ApiResponse::error('Domain not found', 404);
            return;
        }

        $data = ApiMiddleware::getJsonBody();
        try {
            // Fields missing from the body keep their stored values.
            $form = Domain::fromFormData($data + [
                'description' => $existing->description,
                'active' => $existing->active,
                'maxQuota' => $existing->maxQuota,
                'quota' => $existing->quota,
                'mailboxes' => $existing->mailboxes,
                'aliases' => $existing->aliases,
                'transport' => $existing->transport,
            ]);
        } catch (\InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage());
            return;
        }

        // updateDomain() writes every column; the settings string and the disclaimer stay as stored.
        $existing->applyProfile($form);
        $repo->updateDomain($existing);
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

        MailingListService::deleteDomainLists($domain);
        $repo->deleteDomain($domain, 'api');
        AccountSettingsService::deleteDomain($domain);
        ApiResponse::deleted();
    }
}
