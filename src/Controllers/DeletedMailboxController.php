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

class DeletedMailboxController
{
    /**
     * Displays the list of pending mailbox deletions.
     */
    public static function list(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();

        $settings = Settings::getInstance();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $settings->paginationPerPage;

        $repo = RepositoryFactory::getDeletedMailboxRepository();
        $paginatedResult = $repo->getPendingDeletions($page, $perPage);

        $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);

        $tpl->render('deletedMailboxList.php', [
            'deletedMailboxes' => $paginatedResult->items,
            'paginatedResult' => $paginatedResult,
            'error' => $error,
        ]);
    }

    /**
     * Cancels a pending mailbox deletion.
     */
    public static function cancel(TemplateEngine $tpl, string $id): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();

        try {
            if (!RepositoryFactory::getDeletedMailboxRepository()->cancelDeletion((int) $id)) {
                throw new \RuntimeException(Translator::translate('deletedmbx.msg_not_found'));
            }
            ActivityLogger::log('update', '', '', "Cancelled mailbox deletion #{$id}");
            BaseController::flashSuccess(Translator::translate('deletedmbx.msg_cancelled'));
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = BaseController::errorMessage($e);
        }

        header("Location: /deleted-mailboxes");
        exit;
    }

    /**
     * Reschedules a pending mailbox deletion.
     */
    public static function reschedule(TemplateEngine $tpl, string $id): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();

        $newDate = (string) ($_POST['newDate'] ?? '');

        try {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $newDate);
            if ($date === false || $date->format('Y-m-d') !== $newDate) {
                throw new \RuntimeException(Translator::translate('deletedmbx.msg_invalid_date', ['date' => $newDate]));
            }
            if (!RepositoryFactory::getDeletedMailboxRepository()->reschedule((int) $id, $newDate)) {
                throw new \RuntimeException(Translator::translate('deletedmbx.msg_not_found'));
            }
            ActivityLogger::log('update', '', '', "Rescheduled mailbox deletion #{$id} to {$newDate}");
            BaseController::flashSuccess(Translator::translate('deletedmbx.msg_rescheduled', ['date' => $newDate]));
        } catch (\Exception $e) {
            $_SESSION['flash_error'] = BaseController::errorMessage($e);
        }

        header("Location: /deleted-mailboxes");
        exit;
    }
}
