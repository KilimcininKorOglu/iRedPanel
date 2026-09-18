<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Middleware;
use App\Models\Settings;
use App\Repositories\RepositoryFactory;
use App\Services\ActivityLogger;
use App\Services\MailingListService;
use App\TemplateEngine;

class MailingListController
{
    public static function list(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();

        $settings = Settings::getInstance();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $settings->paginationPerPage;
        $domainFilter = $_GET['domain'] ?? null;

        $repo = RepositoryFactory::getMailingListRepository();
        $paginatedResult = $repo->getMailingListsPaginated($page, $perPage, $domainFilter);
        $domains = RepositoryFactory::getDomainRepository()->getDomains();

        $tpl->render('mailingListList.php', [
            'mailingLists' => $paginatedResult->items,
            'paginatedResult' => $paginatedResult,
            'domains' => $domains,
            'filterDomain' => $domainFilter ?? '',
        ]);
    }

    public static function createForm(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();

        $domains = RepositoryFactory::getDomainRepository()->getDomains();
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();

            try {
                $address = self::createFromPost();
                header("Location: /mailing-lists/{$address}");
                exit;
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
            }
        }

        $tpl->render('mailingListCreate.php', [
            'domains' => $domains,
            'error' => $error,
        ]);
    }

    /**
     * Validates the create form and creates the list.
     *
     * @return string the new list address
     */
    private static function createFromPost(): string
    {
        $localPart = trim($_POST['localPart'] ?? '');
        $domain = trim($_POST['domain'] ?? '');
        if ($localPart === '' || $domain === '') {
            throw new \RuntimeException('Email address and domain are required');
        }

        $address = strtolower($localPart . '@' . $domain);
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException(Translator::translate('common.msg_invalid_email', ['address' => $address]));
        }
        if (RepositoryFactory::getAliasRepository()->isAddressInUse($address)) {
            throw new \RuntimeException(Translator::translate('common.msg_address_in_use', ['address' => $address]));
        }
        self::assertAliasLimit($domain);

        MailingListService::create(
            $address,
            $domain,
            trim($_POST['name'] ?? ''),
            trim($_POST['accessPolicy'] ?? 'public'),
            (int) ($_POST['maxMsgSize'] ?? 0),
        );
        ActivityLogger::logCreate($domain, '', "Created mailing list: {$address}");

        return $address;
    }

    /**
     * Mailing lists count against the domain alias limit.
     */
    private static function assertAliasLimit(string $domain): void
    {
        $domainObj = RepositoryFactory::getDomainRepository()->getDomain($domain);
        if ($domainObj === null || $domainObj->aliases <= 0) {
            return;
        }

        $aliasCount = RepositoryFactory::getAliasRepository()->countAliasesForDomain($domain);
        if ($aliasCount >= $domainObj->aliases) {
            throw new \RuntimeException("Domain alias limit reached ({$aliasCount}/{$domainObj->aliases})");
        }
    }

    public static function view(TemplateEngine $tpl, string $address): void
    {
        $domain = str_contains($address, '@') ? explode('@', $address, 2)[1] : '';
        Middleware::domainAdminRequired($domain);

        $repo = RepositoryFactory::getMailingListRepository();
        $ml = $repo->getMailingList($address);

        if ($ml === null) {
            BaseController::page404($tpl);
            return;
        }

        $owners = $repo->getOwners($address);
        $success = null;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();

            try {
                $action = $_POST['action'] ?? 'updateSettings';

                if ($action === 'updateSettings') {
                    $name = trim($_POST['name'] ?? '');
                    $accessPolicy = trim($_POST['accessPolicy'] ?? 'public');
                    $maxMsgSize = (int) ($_POST['maxMsgSize'] ?? 0);
                    $active = isset($_POST['active']);

                    MailingListService::update($address, $name, $accessPolicy, $maxMsgSize, $active);
                    ActivityLogger::logUpdate($ml->domain, '', "Updated mailing list: {$address}");
                    $success = Translator::translate('mlist.msg_updated');
                } elseif ($action === 'updateOwners') {
                    $ownersRaw = trim($_POST['owners'] ?? '');
                    $newOwners = array_filter(array_map('trim', explode("\n", $ownersRaw)));
                    MailingListService::setOwners($address, $newOwners);
                    ActivityLogger::logUpdate($ml->domain, '', "Updated owners for: {$address}");
                    $success = Translator::translate('mlist.msg_owners_updated');
                }

                $ml = $repo->getMailingList($address);
                $owners = $repo->getOwners($address);
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
            }
        }

        $tpl->render('mailingListView.php', [
            'ml' => $ml,
            'owners' => $owners,
            'success' => $success,
            'error' => $error,
        ]);
    }

    public static function delete(TemplateEngine $tpl, string $address): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();

        $repo = RepositoryFactory::getMailingListRepository();
        try {
            $ml = $repo->getMailingList($address) ?? throw BaseController::itemNotFound();
            MailingListService::delete($address);
            ActivityLogger::logDelete($ml->domain, '', "Deleted mailing list: {$address}");
        } catch (\Exception $e) {
            BaseController::flashItemError($address, $e);
        }

        header("Location: /mailing-lists");
        exit;
    }

    public static function bulkAction(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();

        $action = $_POST['action'] ?? '';
        $selected = $_POST['selected'] ?? [];

        if (!is_array($selected) || !in_array($action, BaseController::BULK_ACTIONS, true)) {
            header("Location: /mailing-lists");
            exit;
        }

        $repo = RepositoryFactory::getMailingListRepository();
        $done = BaseController::runBulk($selected, function (string $address) use ($repo, $action): void {
            $repo->getMailingList($address) ?? throw BaseController::itemNotFound();
            if ($action === 'delete') {
                MailingListService::delete($address);
            } else {
                $repo->enableDisableMailingList($address, $action === 'enable');
            }
        });

        if ($done !== []) {
            ActivityLogger::log($action, '', '', "Bulk {$action} on " . count($done) . " mailing lists");
        }
        header("Location: /mailing-lists");
        exit;
    }
}
