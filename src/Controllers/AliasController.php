<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Middleware;
use App\Models\Alias;
use App\Models\Settings;
use App\Repositories\RepositoryFactory;
use App\Services\AccountRenameService;
use App\Services\AccountSettingsService;
use App\Services\ActivityLogger;
use App\Services\Replication\ReplicatedAccountGuard;
use App\TemplateEngine;
use App\Utils\FormValue;

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
                $name = trim($_POST['name'] ?? '');
                $accessPolicy = BaseController::postedAccessPolicy();
                [$address, $domain] = BaseController::postedNewAliasAddress();
                $members = BaseController::postedAddresses('members');

                RepositoryFactory::getAliasRepository()->createAlias($address, $domain, $name, $members, $accessPolicy);
                ActivityLogger::logCreate($domain, '', "Created mail alias: {$address}");
                BaseController::flashCreated($address);

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
                self::applyAction($alias, $members);
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
            'managed' => ReplicatedAccountGuard::isGroupLocked($address),
            'success' => $success,
            'error' => $error,
        ]);
    }

    /**
     * Runs one posted change of the alias view. The directory owns the name and
     * the members of a replicated group, so they keep their stored values.
     *
     * @param string[] $members the stored members
     */
    private static function applyAction(Alias $alias, array $members): void
    {
        match ($_POST['action'] ?? 'update') {
            'addMember' => self::changeMember($alias, true),
            'removeMember' => self::changeMember($alias, false),
            'updateSettings' => self::updateSettings($alias, $members),
            'updateModerators' => self::updateModerators($alias),
            default => null,
        };
    }

    private static function changeMember(Alias $alias, bool $add): void
    {
        ReplicatedAccountGuard::assertNotReplicated($alias->address);
        $member = $add ? BaseController::postedAddress('newMember') : ($_POST['member'] ?? '');
        if ($member === null || $member === '') {
            return;
        }

        $repo = RepositoryFactory::getAliasRepository();
        if ($add) {
            $repo->addAliasMember($alias->address, $member);
            ActivityLogger::logUpdate($alias->domain, '', "Added member {$member} to alias {$alias->address}");
            return;
        }
        $repo->removeAliasMember($alias->address, $member);
        ActivityLogger::logUpdate($alias->domain, '', "Removed member {$member} from alias {$alias->address}");
    }

    /**
     * @param string[] $members the stored members
     */
    private static function updateSettings(Alias $alias, array $members): void
    {
        $locked = ReplicatedAccountGuard::isGroupLocked($alias->address);
        $name = $locked ? $alias->name : trim($_POST['name'] ?? '');
        $updatedMembers = $locked ? $members : BaseController::postedAddresses('members');

        RepositoryFactory::getAliasRepository()->updateAlias(
            $alias->address,
            $name,
            $updatedMembers,
            BaseController::postedAccessPolicy(),
            isset($_POST['active']),
        );
        ActivityLogger::logUpdate($alias->domain, '', "Updated alias settings: {$alias->address}");
    }

    private static function updateModerators(Alias $alias): void
    {
        RepositoryFactory::getAliasRepository()->setModerators($alias->address, BaseController::postedAddresses('moderators'));
        ActivityLogger::logUpdate($alias->domain, '', "Updated moderators for alias {$alias->address}");
    }

    /**
     * Renames a mail alias to another free address in the same domain (POST only).
     * The result is shown on the alias page through a session flash message.
     */
    public static function rename(TemplateEngine $tpl, string $address): void
    {
        $domain = str_contains($address, '@') ? explode('@', $address, 2)[1] : '';
        Middleware::domainAdminRequired($domain);
        CsrfProtection::validateToken();

        $target = $address;
        try {
            RepositoryFactory::getAliasRepository()->getAlias($address) ?? throw BaseController::itemNotFound();
            $newLocal = FormValue::text($_POST, 'newLocalPart');
            $target = AccountRenameService::renameAlias($address, "{$newLocal}@{$domain}");
            BaseController::flashSuccess(Translator::translate('alias.msg_renamed', ['address' => $target]));
        } catch (\Exception $e) {
            BaseController::flashError(BaseController::errorMessage($e));
        }

        header("Location: /aliases/{$target}");
        exit;
    }

    public static function delete(TemplateEngine $tpl, string $address): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();

        $repo = RepositoryFactory::getAliasRepository();
        try {
            $alias = $repo->getAlias($address) ?? throw BaseController::itemNotFound();
            $repo->deleteAlias($address);
            AccountSettingsService::deleteAccounts([$address]);
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

        if (!BaseController::isValidBulkRequest($selectedAliases, $action)) {
            header("Location: /aliases");
            exit;
        }

        $repo = RepositoryFactory::getAliasRepository();
        BaseController::runBulk($selectedAliases, function (string $address) use ($repo, $action): void {
            $alias = $repo->getAlias($address) ?? throw BaseController::itemNotFound();
            if ($action === 'delete') {
                $repo->deleteAlias($address);
                AccountSettingsService::deleteAccounts([$address]);
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
