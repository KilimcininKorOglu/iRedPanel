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

    /**
     * Why the pair of domains cannot become an alias domain.
     *
     * @return array{0: string, 1: int}|null the error message and HTTP status, or null when the pair is valid
     */
    private static function createError(string $aliasDomain, string $targetDomain): ?array
    {
        if ($aliasDomain === '' || $targetDomain === '') {
            return ['aliasDomain and targetDomain are required', 400];
        }
        if (!Domain::isValidName($aliasDomain) || $aliasDomain === $targetDomain) {
            return ['aliasDomain must be a valid domain name other than targetDomain', 400];
        }

        $domainRepo = RepositoryFactory::getDomainRepository();
        if ($domainRepo->getDomain($aliasDomain) !== null
            || RepositoryFactory::getDomainAliasRepository()->getAlias($aliasDomain) !== null) {
            return ['aliasDomain already exists as a mail domain or an alias domain', 409];
        }
        if ($domainRepo->getDomain($targetDomain) === null) {
            return ['targetDomain does not exist', 404];
        }

        return null;
    }

    public static function create(): void
    {
        ApiMiddleware::requireGlobalKey();
        ApiMiddleware::requireWriteAccess();
        $data = ApiMiddleware::getJsonBody();
        $aliasDomain = strtolower(trim((string) ($data['aliasDomain'] ?? '')));
        $targetDomain = strtolower(trim((string) ($data['targetDomain'] ?? '')));

        $error = self::createError($aliasDomain, $targetDomain);
        if ($error !== null) {
            ApiResponse::error($error[0], $error[1]);
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
