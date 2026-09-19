<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware;
use App\Repositories\RepositoryFactory;
use App\Services\ActivityLogger;
use App\Services\ExportService;
use App\Services\LdifExportService;
use App\TemplateEngine;

class ExportController
{
    public static function domainExport(TemplateEngine $tpl, string $domain): void
    {
        Middleware::domainAdminRequired($domain);

        // A global admin passes the check above for any name, so an unknown domain needs its own 404.
        if (RepositoryFactory::getDomainRepository()->getDomain($domain) === null) {
            BaseController::page404($tpl);
            return;
        }

        $format = $_GET['format'] ?? 'csv';
        ExportService::exportDomainUsers($domain, $format);
        exit;
    }

    public static function adminStats(): void
    {
        Middleware::globalAdminRequired();

        $format = $_GET['format'] ?? 'csv';
        ExportService::exportAdminStats($format);
        exit;
    }

    /**
     * Downloads the LDAP subtree of one domain as an LDIF file.
     */
    public static function domainLdif(TemplateEngine $tpl, string $domain): void
    {
        Middleware::domainAdminRequired($domain);
        if (!LdifExportService::available() || RepositoryFactory::getDomainRepository()->getDomain($domain) === null) {
            BaseController::page404($tpl);
            return;
        }

        ActivityLogger::log('export', $domain, '', "Exported the LDAP entries of {$domain}");
        LdifExportService::download("{$domain}.ldif", LdifExportService::domain($domain));
        exit;
    }

    /**
     * Downloads the whole LDAP tree as an LDIF file.
     */
    public static function treeLdif(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();
        if (!LdifExportService::available()) {
            BaseController::page404($tpl);
            return;
        }

        ActivityLogger::log('export', '', '', 'Exported the LDAP tree');
        LdifExportService::download('ldap-tree.ldif', LdifExportService::tree());
        exit;
    }
}
