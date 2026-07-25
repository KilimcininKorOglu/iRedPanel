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
                $localPart = trim($_POST['localPart'] ?? '');
                $domain = trim($_POST['domain'] ?? '');
                $name = trim($_POST['name'] ?? '');
                $accessPolicy = trim($_POST['accessPolicy'] ?? 'public');
                $maxMsgSize = (int) ($_POST['maxMsgSize'] ?? 0);
                $maxMembers = (int) ($_POST['maxMembers'] ?? 0);

                if ($localPart === '' || $domain === '') {
                    throw new \RuntimeException('Email address and domain are required');
                }

                $address = $localPart . '@' . $domain;
                $repo = RepositoryFactory::getMailingListRepository();

                if ($repo->getMailingList($address) !== null) {
                    throw new \RuntimeException('Mailing list already exists: ' . $address);
                }

                // Enforce domain alias limit (mailing lists count as aliases)
                $domainObj = RepositoryFactory::getDomainRepository()->getDomain($domain);
                if ($domainObj !== null && $domainObj->aliases > 0) {
                    $aliasRepo = RepositoryFactory::getAliasRepository();
                    $aliasCount = $aliasRepo->countAliasesForDomain($domain);
                    if ($aliasCount >= $domainObj->aliases) {
                        throw new \RuntimeException("Domain alias limit reached ({$aliasCount}/{$domainObj->aliases})");
                    }
                }

                $repo->createMailingList($address, $domain, $name, $accessPolicy, $maxMsgSize, $maxMembers);
                ActivityLogger::logCreate('mailinglist', $domain, "Created mailing list: {$address}");

                header("Location: /mailing-lists/{$address}");
                exit;
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        $tpl->render('mailingListCreate.php', [
            'domains' => $domains,
            'error' => $error,
        ]);
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
                    $maxMembers = (int) ($_POST['maxMembers'] ?? 0);
                    $active = isset($_POST['active']);

                    $repo->updateMailingList($address, $name, $accessPolicy, $maxMsgSize, $maxMembers, $active);
                    ActivityLogger::logUpdate('mailinglist', $ml->domain, "Updated mailing list: {$address}");
                    $success = Translator::translate('mlist.msg_updated');
                } elseif ($action === 'updateOwners') {
                    $ownersRaw = trim($_POST['owners'] ?? '');
                    $newOwners = array_filter(array_map('trim', explode("\n", $ownersRaw)));
                    $repo->setOwners($address, $newOwners);
                    ActivityLogger::logUpdate('mailinglist', $ml->domain, "Updated owners for: {$address}");
                    $success = Translator::translate('mlist.msg_owners_updated');
                }

                $ml = $repo->getMailingList($address);
                $owners = $repo->getOwners($address);
            } catch (\Exception $e) {
                $error = $e->getMessage();
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
        $ml = $repo->getMailingList($address);

        if ($ml !== null) {
            $repo->deleteMailingList($address);
            ActivityLogger::logDelete('mailinglist', $ml->domain, "Deleted mailing list: {$address}");
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

        if (empty($selected)) {
            header("Location: /mailing-lists");
            exit;
        }

        $repo = RepositoryFactory::getMailingListRepository();

        foreach ($selected as $address) {
            if ($action === 'enable') {
                $repo->enableDisableMailingList($address, true);
            } elseif ($action === 'disable') {
                $repo->enableDisableMailingList($address, false);
            } elseif ($action === 'delete') {
                $repo->deleteMailingList($address);
            }
        }

        ActivityLogger::log($action, '', '', "Bulk {$action} on " . count($selected) . " mailing lists");
        header("Location: /mailing-lists");
        exit;
    }
}
