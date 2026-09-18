<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Middleware;
use App\Models\Settings;
use App\Repositories\RepositoryFactory;
use App\Services\ActivityLogger;
use App\TemplateEngine;

class AmavisdController
{
    public static function quarantineList(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();
        self::requireEnabled();

        $settings = Settings::getInstance();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $settings->paginationPerPage;
        $domain = $_GET['domain'] ?? null;

        $repo = RepositoryFactory::getAmavisdRepository();
        $paginatedResult = $repo->getQuarantinedMessages($page, $perPage, $domain);

        $tpl->render('quarantineList.php', [
            'messages' => $paginatedResult->items,
            'paginatedResult' => $paginatedResult,
            'filterDomain' => $domain ?? '',
        ]);
    }

    public static function releaseMessage(TemplateEngine $tpl, string $mailId): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();
        self::requireEnabled();

        try {
            $repo = RepositoryFactory::getAmavisdRepository();
            $repo->releaseMessage($mailId, $_SESSION['email'] ?? 'iredpanel');
            ActivityLogger::log('update', '', '', "Released quarantined message: {$mailId}");
            BaseController::flashSuccess(Translator::translate('quarantine.msg_released', ['id' => $mailId]));
        } catch (\Exception $e) {
            error_log("Amavisd release failed: " . $e->getMessage());
            BaseController::flashItemError($mailId, $e);
        }

        header("Location: /amavisd/quarantine");
        exit;
    }

    public static function deleteMessage(TemplateEngine $tpl, string $mailId): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();
        self::requireEnabled();

        try {
            $repo = RepositoryFactory::getAmavisdRepository();
            $repo->deleteQuarantinedMessage($mailId);
            ActivityLogger::log('delete', '', '', "Deleted quarantined message: {$mailId}");
            BaseController::flashSuccess(Translator::translate('quarantine.msg_deleted', ['id' => $mailId]));
        } catch (\Exception $e) {
            error_log("Amavisd delete failed: " . $e->getMessage());
            BaseController::flashItemError($mailId, $e);
        }

        header("Location: /amavisd/quarantine");
        exit;
    }

    public static function mailLog(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();
        self::requireEnabled();

        $settings = Settings::getInstance();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $settings->paginationPerPage;
        $email = $_GET['email'] ?? null;

        $repo = RepositoryFactory::getAmavisdRepository();
        $paginatedResult = $repo->getMailLog($page, $perPage, $email);

        $tpl->render('mailLog.php', [
            'entries' => $paginatedResult->items,
            'paginatedResult' => $paginatedResult,
            'filterEmail' => $email ?? '',
        ]);
    }

    public static function cleanup(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();
        self::requireEnabled();

        $settings = Settings::getInstance();
        $repo = RepositoryFactory::getAmavisdRepository();
        $quarantineDeleted = $repo->cleanupQuarantined($settings->amavisdRemoveQuarantinedInDays);
        $logDeleted = $repo->cleanupMailLog($settings->amavisdRemoveMaillogInDays);

        ActivityLogger::log('delete', '', '', "Amavisd cleanup: {$quarantineDeleted} quarantine + {$logDeleted} log records");
        BaseController::flashSuccess(Translator::translate('quarantine.msg_cleanup_done', [
            'quarantine' => $quarantineDeleted,
            'log' => $logDeleted,
        ]));

        header("Location: /amavisd/quarantine");
        exit;
    }

    private static function requireEnabled(): void
    {
        if (!Settings::getInstance()->amavisdEnabled) {
            http_response_code(403);
            echo 'Amavisd integration is not enabled';
            exit;
        }
    }
}
