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

class AliasController
{
    public static function list(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();

        $settings = Settings::getInstance();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $settings->paginationPerPage;
        $domainFilter = $_GET['domain'] ?? null;

        $repo = RepositoryFactory::getAliasRepository();
        $paginatedResult = $repo->getAliasesPaginated($page, $perPage, $domainFilter);

        $domains = RepositoryFactory::getDomainRepository()->getDomains();

        $tpl->render('aliasList.php', [
            'aliases' => $paginatedResult->items,
            'paginatedResult' => $paginatedResult,
            'domains' => $domains,
            'filterDomain' => $domainFilter ?? '',
        ]);
    }

    public static function createForm(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();

        $domains = RepositoryFactory::getDomainRepository()->getDomains();
        $success = null;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();

            try {
                $localPart = trim($_POST['localPart'] ?? '');
                $domain = trim($_POST['domain'] ?? '');
                $name = trim($_POST['name'] ?? '');
                $accessPolicy = trim($_POST['accessPolicy'] ?? 'public');

                if ($localPart === '' || $domain === '') {
                    throw new \RuntimeException(Translator::translate('common.msg_address_required'));
                }

                $address = strtolower($localPart . '@' . $domain);
                $members = BaseController::postedAddresses('members');

                $repo = RepositoryFactory::getAliasRepository();

                if ($repo->isAddressInUse($address)) {
                    throw new \RuntimeException(Translator::translate('common.msg_address_in_use', ['address' => $address]));
                }

                // Enforce domain alias limit
                $domainObj = RepositoryFactory::getDomainRepository()->getDomain($domain);
                if ($domainObj !== null && $domainObj->aliases > 0) {
                    $aliasCount = $repo->countAliasesForDomain($domain);
                    if ($aliasCount >= $domainObj->aliases) {
                        throw new \RuntimeException(Translator::translate('common.msg_alias_limit', [
                            'current' => $aliasCount,
                            'max' => $domainObj->aliases,
                        ]));
                    }
                }

                $repo->createAlias($address, $domain, $name, $members, $accessPolicy);
                ActivityLogger::logCreate($domain, '', "Created mail alias: {$address}");

                header("Location: /aliases/{$address}");
                exit;
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
            }
        }

        $tpl->render('aliasCreate.php', [
            'domains' => $domains,
            'success' => $success,
            'error' => $error,
        ]);
    }

    public static function view(TemplateEngine $tpl, string $address): void
    {
        $domain = str_contains($address, '@') ? explode('@', $address, 2)[1] : '';
        Middleware::domainAdminRequired($domain);

        $repo = RepositoryFactory::getAliasRepository();
        $alias = $repo->getAlias($address);

        if ($alias === null) {
            BaseController::page404($tpl);
            return;
        }

        $members = $repo->getAliasMembers($address);
        $moderators = $repo->getModerators($address);
        $success = null;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();

            try {
                $action = $_POST['action'] ?? 'update';

                if ($action === 'addMember') {
                    $newMember = BaseController::postedAddress('newMember');
                    if ($newMember !== null) {
                        $repo->addAliasMember($address, $newMember);
                        ActivityLogger::logUpdate($alias->domain, '', "Added member {$newMember} to alias {$address}");
                    }
                } elseif ($action === 'removeMember') {
                    $memberToRemove = $_POST['member'] ?? '';
                    if ($memberToRemove !== '') {
                        $repo->removeAliasMember($address, $memberToRemove);
                        ActivityLogger::logUpdate($alias->domain, '', "Removed member {$memberToRemove} from alias {$address}");
                    }
                } elseif ($action === 'updateSettings') {
                    $name = trim($_POST['name'] ?? '');
                    $accessPolicy = trim($_POST['accessPolicy'] ?? 'public');
                    $active = isset($_POST['active']);
                    $updatedMembers = BaseController::postedAddresses('members');

                    $repo->updateAlias($address, $name, $updatedMembers, $accessPolicy, $active);
                    ActivityLogger::logUpdate($alias->domain, '', "Updated alias settings: {$address}");
                } elseif ($action === 'updateModerators') {
                    $newModerators = BaseController::postedAddresses('moderators');

                    $repo->setModerators($address, $newModerators);
                    ActivityLogger::logUpdate($alias->domain, '', "Updated moderators for alias {$address}");
                }

                $success = Translator::translate('alias.msg_updated');
                $alias = $repo->getAlias($address);
                $members = $repo->getAliasMembers($address);
                $moderators = $repo->getModerators($address);
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
            }
        }

        $tpl->render('aliasView.php', [
            'alias' => $alias,
            'members' => $members,
            'moderators' => $moderators,
            'success' => $success,
            'error' => $error,
        ]);
    }

    public static function delete(TemplateEngine $tpl, string $address): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();

        $repo = RepositoryFactory::getAliasRepository();
        try {
            $alias = $repo->getAlias($address) ?? throw BaseController::itemNotFound();
            $repo->deleteAlias($address);
            ActivityLogger::logDelete($alias->domain, '', "Deleted mail alias: {$address}");
            BaseController::flashDeleted($address);
        } catch (\Exception $e) {
            BaseController::flashItemError($address, $e);
        }

        header("Location: /aliases");
        exit;
    }

    public static function bulkAction(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();

        $action = $_POST['action'] ?? '';
        $selectedAliases = $_POST['selected'] ?? [];

        if (!is_array($selectedAliases) || !in_array($action, BaseController::BULK_ACTIONS, true)) {
            header("Location: /aliases");
            exit;
        }

        $repo = RepositoryFactory::getAliasRepository();
        BaseController::runBulk($selectedAliases, function (string $address) use ($repo, $action): void {
            $alias = $repo->getAlias($address) ?? throw BaseController::itemNotFound();
            if ($action === 'delete') {
                $repo->deleteAlias($address);
                ActivityLogger::logDelete($alias->domain, '', "Deleted alias: {$address}");
                return;
            }
            $repo->enableDisableAlias($address, $action === 'enable');
            ActivityLogger::logUpdate($alias->domain, '', ucfirst($action) . "d alias: {$address}");
        });

        header("Location: /aliases");
        exit;
    }
}
