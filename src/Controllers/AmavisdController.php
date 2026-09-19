<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Middleware;
use App\Models\Settings;
use App\Repositories\RepositoryFactory;
use App\Services\ActivityLogger;
use App\Services\AdminLimits;
use App\TemplateEngine;

class AmavisdController
{
    public static function quarantineList(TemplateEngine $tpl): void
    {
        $domain = self::openPage('disableManagingQuarantinedMails');

        $settings = Settings::getInstance();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $settings->paginationPerPage;

        $repo = RepositoryFactory::getAmavisdRepository();
        $paginatedResult = $repo->getQuarantinedMessages($page, $perPage, $domain);

        $tpl->render('quarantineList.php', [
            'messages' => $paginatedResult->items,
            'paginatedResult' => $paginatedResult,
            'filterDomain' => $domain ?? '',
            'domains' => BaseController::managedDomainRows(),
        ]);
    }

    public static function releaseMessage(TemplateEngine $tpl, string $mailId): void
    {
        self::handleQuarantined($mailId, true);
    }

    public static function deleteMessage(TemplateEngine $tpl, string $mailId): void
    {
        self::handleQuarantined($mailId, false);
    }

    /**
     * Releases or deletes a quarantined message (POST only). A global admin handles the
     * whole message; a domain admin handles the copies of the recipients in the domain of
     * the list page, so the copies of other domains stay in the quarantine.
     */
    private static function handleQuarantined(string $mailId, bool $release): void
    {
        self::openPage('disableManagingQuarantinedMails');
        CsrfProtection::validateToken();

        try {
            $repo = RepositoryFactory::getAmavisdRepository();
            if (Middleware::isGlobalAdmin()) {
                $release ? $repo->releaseMessage($mailId, $_SESSION['email'] ?? 'iredpanel') : $repo->deleteQuarantinedMessage($mailId);
            } else {
                self::handleDomainCopies($mailId, $release);
            }
            ActivityLogger::log($release ? 'update' : 'delete', '', '', ($release ? 'Released' : 'Deleted') . " quarantined message: {$mailId}");
            BaseController::flashSuccess(Translator::translate($release ? 'quarantine.msg_released' : 'quarantine.msg_deleted', ['id' => $mailId]));
        } catch (\Exception $e) {
            error_log('Amavisd ' . ($release ? 'release' : 'delete') . ' failed: ' . $e->getMessage());
            BaseController::flashItemError($mailId, $e);
        }

        header('Location: ' . BaseController::listUrl('/amavisd/quarantine'));
        exit;
    }

    /**
     * @throws \RuntimeException when no recipient of the domain waits for the message
     */
    private static function handleDomainCopies(string $mailId, bool $release): void
    {
        $domain = is_string($_POST['filterDomain'] ?? null) ? $_POST['filterDomain'] : '';
        Middleware::domainAdminRequired($domain);

        $repo = RepositoryFactory::getAmavisdRepository();
        $recipients = $repo->pendingQuarantineRecipients($mailId, $domain);
        if ($recipients === []) {
            throw BaseController::itemNotFound();
        }
        foreach ($recipients as $recipient) {
            $release ? $repo->releaseForRecipient($mailId, $recipient) : $repo->deleteForRecipient($mailId, $recipient);
        }
    }

    public static function mailLog(TemplateEngine $tpl): void
    {
        $domain = self::openPage('disableViewingMailLog');

        $settings = Settings::getInstance();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $settings->paginationPerPage;
        $email = is_string($_GET['email'] ?? null) ? $_GET['email'] : null;

        $repo = RepositoryFactory::getAmavisdRepository();
        $paginatedResult = $repo->getMailLog($page, $perPage, $email, $domain);

        $tpl->render('mailLog.php', [
            'entries' => $paginatedResult->items,
            'paginatedResult' => $paginatedResult,
            'filterEmail' => $email ?? '',
            'filterDomain' => $domain ?? '',
            'domains' => BaseController::managedDomainRows(),
        ]);
    }

    /**
     * Checks the access to a quarantine or mail log page: the integration is on, and the
     * admin is a global admin or a domain admin whose permission toggle leaves the page open.
     *
     * @return string|null the domain filter; a domain admin always gets one of its domains
     */
    private static function openPage(string $permission): ?string
    {
        $domain = BaseController::listDomainFilter();
        self::requireEnabled();
        if (!AdminLimits::allows($permission)) {
            http_response_code(403);
            echo 'Access denied: this page is disabled for your admin account';
            exit;
        }

        return $domain;
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
