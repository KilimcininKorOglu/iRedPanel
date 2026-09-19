<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware;
use App\Repositories\RepositoryFactory;
use App\Services\CompatibilityService;
use App\TemplateEngine;
use App\Utils\SystemInfo;

class DashboardController
{
    /**
     * Displays the dashboard with statistics.
     */
    public static function dashboard(TemplateEngine $tpl): void
    {
        Middleware::loginRequired();

        $stats = Middleware::isGlobalAdmin()
            ? RepositoryFactory::getDashboardRepository()->getStats()
            : [];

        $systemInfo = null;
        $compatibility = null;
        if (Middleware::isGlobalAdmin()) {
            $systemInfo = [
                'hostname' => SystemInfo::getHostname(),
                'uptime' => SystemInfo::getUptime(),
                'loadAverage' => SystemInfo::getLoadAverage(),
                'phpVersion' => SystemInfo::getPhpVersion(),
                'iredpanelVersion' => SystemInfo::getIredPanelVersion(),
            ];
            $compatibility = self::compatibility($systemInfo['iredpanelVersion']);
        }

        $tpl->render('dashboard.php', [
            'stats' => $stats,
            'systemInfo' => $systemInfo,
            'compatibility' => $compatibility,
        ]);
    }

    /**
     * The compatibility list for the installed version. A list that cannot be read
     * is reported on the dashboard instead of breaking the page.
     */
    private static function compatibility(string $installedVersion): array
    {
        try {
            $list = CompatibilityService::load();
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            error_log('iRedPanel: ' . $e->getMessage());
            return ['error' => true];
        }

        return CompatibilityService::forVersion($list['releases'], $installedVersion)
            + ['source' => $list['source'], 'checkedAt' => $list['checkedAt'], 'error' => false];
    }
}
