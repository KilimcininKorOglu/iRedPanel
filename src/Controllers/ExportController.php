<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware;
use App\Repositories\RepositoryFactory;
use App\Services\ExportService;
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
}
