<?php

declare(strict_types=1);

namespace App\Api;

use App\Models\Domain;
use App\Models\DomainAlias;
use App\Repositories\RepositoryFactory;

class DomainAliasApiController
{
    public static function list(): void
    {
        ApiMiddleware::requireGlobalKey();
        $repo = RepositoryFactory::getDomainAliasRepository();
        $result = $repo->getAllAliasesPaginated(
            max(1, (int) ($_GET['page'] ?? 1)),
            (int) ($_GET['perPage'] ?? 50)
        );
        ApiResponse::paginated($result, fn(DomainAlias $a) => [
            'aliasDomain' => $a->aliasDomain,
            'targetDomain' => $a->targetDomain,
            'active' => $a->active,
        ]);
    }

    public static function create(): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $data = ApiMiddleware::getJsonBody();
        $aliasDomain = strtolower(trim((string) ($data['aliasDomain'] ?? '')));
        $targetDomain = strtolower(trim((string) ($data['targetDomain'] ?? '')));

        if ($aliasDomain === '' || $targetDomain === '') {
            ApiResponse::error('aliasDomain and targetDomain are required');
            return;
        }
        if (!Domain::isValidName($aliasDomain) || $aliasDomain === $targetDomain) {
            ApiResponse::error('aliasDomain must be a valid domain name other than targetDomain');
            return;
        }

        $domainRepo = RepositoryFactory::getDomainRepository();
        if ($domainRepo->getDomain($aliasDomain) !== null
            || RepositoryFactory::getDomainAliasRepository()->getAlias($aliasDomain) !== null) {
            ApiResponse::error('aliasDomain already exists as a mail domain or an alias domain', 409);
            return;
        }
        if ($domainRepo->getDomain($targetDomain) === null) {
            ApiResponse::error('targetDomain does not exist', 404);
            return;
        }

        $aliasRepo = RepositoryFactory::getDomainAliasRepository();
        $active = (bool) ($data['active'] ?? true);
        if (!$active && !$aliasRepo->supportsStatus()) {
            ApiResponse::error('active=false is not supported by this backend');
            return;
        }

        $aliasRepo->createAlias(new DomainAlias($aliasDomain, $targetDomain, $active));
        ApiResponse::created(['aliasDomain' => $aliasDomain]);
    }

    public static function delete(string $aliasDomain): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        RepositoryFactory::getDomainAliasRepository()->deleteAlias($aliasDomain);
        ApiResponse::deleted();
    }
}
