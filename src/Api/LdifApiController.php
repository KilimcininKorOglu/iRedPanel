<?php

declare(strict_types=1);

namespace App\Api;

use App\Repositories\RepositoryFactory;
use App\Services\LdifExportService;

/**
 * Serves the LDAP entries as LDIF text. The answer is not JSON, because an
 * LDIF file goes straight into `ldapadd`.
 */
class LdifApiController
{
    public static function tree(): void
    {
        ApiMiddleware::requireGlobalKey();
        if (!self::requireLdapBackend()) {
            return;
        }

        self::send(LdifExportService::tree());
    }

    public static function domain(string $domain): void
    {
        ApiMiddleware::requireGlobalKey();
        if (!self::requireLdapBackend()) {
            return;
        }
        if (RepositoryFactory::getDomainRepository()->getDomain($domain) === null) {
            ApiResponse::error('Domain not found', 404);
            return;
        }

        self::send(LdifExportService::domain($domain));
    }

    private static function requireLdapBackend(): bool
    {
        if (LdifExportService::available()) {
            return true;
        }
        ApiResponse::error('LDIF export needs the LDAP backend');

        return false;
    }

    private static function send(string $ldif): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo $ldif;
    }
}
