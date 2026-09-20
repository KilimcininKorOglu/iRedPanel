<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware;
use App\Models\DomainSettings;
use App\Models\ProfileToggles;
use App\Models\Settings;
use App\Repositories\RepositoryFactory;
use App\Services\DnsCheck;
use App\TemplateEngine;
use App\Utils\SystemDnsLookup;

/**
 * The mail DNS records of one domain. The page reads DNS only and writes nothing,
 * so a domain admin opens it for a managed domain as well.
 */
class DomainDnsController
{
    public static function dnsCheck(TemplateEngine $tpl, string $domainName): void
    {
        Middleware::domainAdminRequired($domainName);

        $domain = RepositoryFactory::getDomainRepository()->getDomain($domainName);
        if ($domain === null) {
            http_response_code(404);
            $tpl->render('page404.php');
            return;
        }

        $check = new DnsCheck(new SystemDnsLookup(), Settings::getInstance()->dkimSelector);

        $tpl->render('domainDns.php', [
            'domain' => $domain,
            'dnsRows' => $check->run($domainName),
            'dkimName' => $check->dkimName($domainName),
            'openPages' => ProfileToggles::openDomainPages(
                DomainSettings::fromSettingsString($domain->settings),
                Middleware::isGlobalAdmin()
            ),
        ]);
    }
}
