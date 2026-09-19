<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Middleware;
use App\Models\MailList;
use App\Models\Settings;
use App\Repositories\MailListRepositoryInterface;
use App\Repositories\RepositoryFactory;
use App\Services\AccountSettingsService;
use App\Services\ActivityLogger;
use App\Services\AdminLimits;
use App\TemplateEngine;

/**
 * Mail lists of the LDAP backend: group accounts whose members the admin
 * manages. A member never subscribes or unsubscribes itself.
 */
class MailListController
{
    public static function list(TemplateEngine $tpl): void
    {
        $repo = self::repository($tpl);
        $domainFilter = BaseController::listDomainFilter();
        [$statusFilter, $activeOnly] = BaseController::statusFilter();
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $domains = BaseController::managedDomainRows();
        $scope = $domainFilter !== null
            ? [$domainFilter]
            : (Middleware::isGlobalAdmin() ? null : array_column($domains, 'domainName'));

        $paginatedResult = $repo->getMailListsPaginated($scope, $page, Settings::getInstance()->paginationPerPage, $activeOnly);

        $tpl->render('mailListList.php', [
            'mailLists' => $paginatedResult->items,
            'paginatedResult' => $paginatedResult,
            'domains' => $domains,
            'filterDomain' => $domainFilter ?? '',
            'statusFilter' => $statusFilter,
        ]);
    }

    public static function createForm(TemplateEngine $tpl): void
    {
        $repo = self::repository($tpl);
        Middleware::anyDomainAdminRequired();
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();
            try {
                $address = self::createFromPost($repo);
                BaseController::flashCreated($address);
                header('Location: /mail-lists/' . $address);
                exit;
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
            }
        }

        $tpl->render('mailListCreate.php', [
            'domains' => BaseController::managedDomainRows(),
            'error' => $error,
        ]);
    }

    public static function view(TemplateEngine $tpl, string $address): void
    {
        $repo = self::repository($tpl);
        Middleware::domainAdminRequired(self::domainOf($address));

        $list = $repo->getMailList($address);
        if ($list === null) {
            BaseController::page404($tpl);
            return;
        }

        $success = null;
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();
            try {
                $repo->updateMailList(MailList::fromFormData($address, $_POST));
                ActivityLogger::logUpdate($list->domain, '', "Updated mail list: {$address}");
                $success = Translator::translate('maillist.msg_updated');
                $list = $repo->getMailList($address);
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
            }
        }

        $tpl->render('mailListView.php', [
            'mailList' => $list,
            'success' => $success,
            'error' => $error,
        ]);
    }

    public static function delete(TemplateEngine $tpl, string $address): void
    {
        $repo = self::repository($tpl);
        Middleware::domainAdminRequired(self::domainOf($address));
        CsrfProtection::validateToken();

        try {
            $list = $repo->getMailList($address) ?? throw BaseController::itemNotFound();
            $repo->deleteMailList($address);
            AccountSettingsService::deleteAccounts([$address]);
            ActivityLogger::logDelete($list->domain, '', "Deleted mail list: {$address}");
            BaseController::flashDeleted($address);
        } catch (\Exception $e) {
            BaseController::flashItemError($address, $e);
        }

        header('Location: ' . BaseController::listUrl('/mail-lists'));
        exit;
    }

    /**
     * Creates the list from the posted form and returns its address.
     *
     * @throws \Exception naming the failed check
     */
    private static function createFromPost(MailListRepositoryInterface $repo): string
    {
        [$address, $domain] = BaseController::postedNewAliasAddress();
        AdminLimits::assertCanCreate('aliases');

        $list = MailList::fromFormData($address, $_POST + ['active' => true]);
        $repo->createMailList($list);
        ActivityLogger::logCreate($domain, '', "Created mail list: {$address}");

        return $address;
    }

    /**
     * The repository of the backend. A SQL backend has no mail lists, so the
     * page answers 404 instead of an empty list.
     */
    private static function repository(TemplateEngine $tpl): MailListRepositoryInterface
    {
        Middleware::loginRequired();
        $repo = RepositoryFactory::getMailListRepository();
        if (!$repo->isAvailable()) {
            BaseController::page404($tpl);
            exit;
        }

        return $repo;
    }

    private static function domainOf(string $address): string
    {
        return str_contains($address, '@') ? explode('@', $address, 2)[1] : '';
    }
}
