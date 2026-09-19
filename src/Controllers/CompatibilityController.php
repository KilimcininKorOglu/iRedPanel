<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware;
use App\Services\CompatibilityService;
use App\TemplateEngine;
use App\Utils\SystemInfo;

class CompatibilityController
{
    /**
     * Lists every iRedPanel release with the iRedMail versions it is compatible with.
     */
    public static function view(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();

        $installedVersion = SystemInfo::getIredPanelVersion();

        $tpl->render('compatibility.php', [
            'installedVersion' => $installedVersion,
            'report' => CompatibilityService::report($installedVersion),
        ]);
    }
}
