<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Middleware;
use App\Models\AccountResource;
use App\Models\AccountResourceForm;
use App\Models\PaginatedResult;
use App\Models\Settings;
use App\Repositories\AccountResourceRepositoryInterface;
use App\Repositories\RepositoryFactory;
use App\Services\ActivityLogger;
use App\Services\Replication\ReplicationRunner;
use App\TemplateEngine;
use App\Utils\SecretBox;

/**
 * Account resources: directory servers whose accounts the panel replicates.
 */
class AccountResourceController
{
    public static function list(TemplateEngine $tpl): void
    {
        $repo = self::repository();
        $tpl->render('accountResourceList.php', ['resources' => $repo->all(), 'types' => AccountResource::TYPES]);
    }

    /**
     * Creates a disabled resource with the default settings and opens its Connection tab.
     */
    public static function create(TemplateEngine $tpl): void
    {
        $repo = self::repository();
        CsrfProtection::validateToken();
        $type = (string) ($_POST['type'] ?? '');
        if (!in_array($type, AccountResource::TYPES, true)) {
            BaseController::flashError(Translator::translate('resource.msg_invalid_type'));
            self::redirect('/account-resources');
        }
        // It stays disabled until the connection settings are saved and the admin enables it.
        $id = $repo->save(new AccountResource(type: $type, enabled: false));
        ActivityLogger::log('create', '', '', "Created account resource #{$id} ({$type})");
        self::redirect("/account-resources/{$id}?tab=connection");
    }

    public static function edit(TemplateEngine $tpl, string $id): void
    {
        $resource = self::find(self::repository(), $id);
        self::renderEdit($tpl, $resource, self::tab($_GET['tab'] ?? 'connection'));
    }

    public static function save(TemplateEngine $tpl, string $id): void
    {
        $repo = self::repository();
        CsrfProtection::validateToken();
        $resource = self::find($repo, $id);
        $tab = self::tab($_POST['tab'] ?? '');
        try {
            self::applyForm($resource, $tab, $_POST);
            $repo->save($resource);
            ActivityLogger::log('update', $resource->domain, '', "Updated account resource #{$resource->id}: {$tab}");
            BaseController::flashSuccess(Translator::translate('resource.msg_saved'));
        } catch (\Exception $e) {
            self::renderEdit($tpl, $resource, $tab, error: BaseController::errorMessage($e));
            return;
        }
        self::redirect("/account-resources/{$resource->id}?tab={$tab}");
    }

    /**
     * Tests the posted connection settings without saving them.
     */
    public static function test(TemplateEngine $tpl, string $id): void
    {
        $repo = self::repository();
        CsrfProtection::validateToken();
        $resource = self::find($repo, $id);
        $preview = null;
        $error = null;
        try {
            $password = AccountResourceForm::apply($resource, 'connection', $_POST);
            $preview = ReplicationRunner::preview($resource, $password ?? self::storedPassword($resource));
        } catch (\Exception $e) {
            $error = BaseController::errorMessage($e);
        }
        self::renderEdit($tpl, $resource, 'connection', $preview, $error);
    }

    public static function replicate(TemplateEngine $tpl, string $id): void
    {
        $repo = self::repository();
        CsrfProtection::validateToken();
        $resource = self::find($repo, $id);
        try {
            $result = ReplicationRunner::create()->run($resource, (bool) ($_POST['allowMassDisable'] ?? false));
        } catch (\Exception $e) {
            BaseController::flashError(Translator::translate('resource.msg_run_failed', ['reason' => BaseController::errorMessage($e)]));
            self::redirect("/account-resources/{$resource->id}/log");
        }
        if ($result === null) {
            BaseController::flashError(Translator::translate('resource.msg_running'));
        } elseif ($result->status() === AccountResource::STATUS_FAILED) {
            BaseController::flashError(Translator::translate('resource.msg_run_failed', ['reason' => $result->message()]));
        } else {
            BaseController::flashSuccess(Translator::translate('resource.msg_run_done', ['summary' => self::summary($result->counts)]));
        }
        self::redirect("/account-resources/{$resource->id}/log");
    }

    public static function toggle(TemplateEngine $tpl, string $id): void
    {
        $repo = self::repository();
        CsrfProtection::validateToken();
        $resource = self::find($repo, $id);
        $enable = !$resource->enabled;
        if ($enable && ($resource->host === '' || $resource->bindPassword === '')) {
            BaseController::flashError(Translator::translate('resource.msg_incomplete'));
            self::redirect('/account-resources');
        }
        $repo->setEnabled($resource->id, $enable);
        ActivityLogger::log($enable ? 'enable' : 'disable', $resource->domain, '', "Account resource #{$resource->id}");
        BaseController::flashSuccess(Translator::translate($enable ? 'resource.msg_enabled' : 'resource.msg_disabled'));
        self::redirect('/account-resources');
    }

    public static function delete(TemplateEngine $tpl, string $id): void
    {
        $repo = self::repository();
        CsrfProtection::validateToken();
        $resource = self::find($repo, $id);
        $repo->delete($resource->id);
        ActivityLogger::log('delete', $resource->domain, '', "Deleted account resource #{$resource->id} ({$resource->host})");
        BaseController::flashDeleted(Translator::translate('resource.item', ['host' => $resource->host ?: "#{$resource->id}"]));
        self::redirect('/account-resources');
    }

    public static function log(TemplateEngine $tpl, string $id): void
    {
        $repo = self::repository();
        $resource = self::find($repo, $id);
        $perPage = Settings::getInstance()->paginationPerPage;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $runs = $repo->runs($resource->id, $perPage, ($page - 1) * $perPage);
        $selected = (int) ($_GET['run'] ?? ($runs[0]['id'] ?? 0));

        $tpl->render('accountResourceLog.php', [
            'resource' => $resource,
            'runs' => $runs,
            'paginatedResult' => new PaginatedResult($runs, $repo->countRuns($resource->id), $page, $perPage),
            'selectedRun' => $selected,
            'events' => $selected > 0 ? $repo->events($selected) : [],
        ]);
    }

    /**
     * @param ?array{users: array, groups: array} $preview
     */
    private static function renderEdit(TemplateEngine $tpl, AccountResource $resource, string $tab, ?array $preview = null, ?string $error = null): void
    {
        $tpl->render('accountResourceEdit.php', [
            'resource' => $resource,
            'posted' => $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : null,
            'activeTab' => $tab === 'groups' && !$resource->replicateGroups ? 'connection' : $tab,
            'domains' => RepositoryFactory::getDomainRepository()->getDomains(),
            'preview' => $preview,
            'error' => $error,
            'hasPassword' => $resource->bindPassword !== '',
        ]);
    }

    /**
     * @throws \App\Exceptions\InvalidInputException for an invalid field
     */
    private static function applyForm(AccountResource $resource, string $tab, array $post): void
    {
        $password = AccountResourceForm::apply($resource, $tab, $post);
        if ($tab === 'connection' && RepositoryFactory::getDomainRepository()->getDomain($resource->domain) === null) {
            throw new \RuntimeException(Translator::translate('common.msg_domain_not_found', ['domain' => $resource->domain]));
        }
        if ($password !== null) {
            $resource->bindPassword = SecretBox::fromSettings()->encrypt($password);
        }
    }

    private static function storedPassword(AccountResource $resource): string
    {
        if ($resource->bindPassword === '') {
            throw new \RuntimeException(Translator::translate('resource.msg_no_password'));
        }
        try {
            return SecretBox::fromSettings()->decrypt($resource->bindPassword);
        } catch (\App\Exceptions\SecretBoxException) {
            throw new \RuntimeException(Translator::translate('resource.msg_password_unreadable'));
        }
    }

    /**
     * @param array<string, int> $counts
     */
    private static function summary(array $counts): string
    {
        if ($counts === []) {
            return Translator::translate('resource.no_changes');
        }

        return implode(', ', array_map(
            static fn(string $action, int $n): string => Translator::translate("resource.action_{$action}") . " {$n}",
            array_keys($counts),
            $counts,
        ));
    }

    /**
     * Checks the access and the iredadmin database, and creates the tables on first use.
     */
    private static function repository(): AccountResourceRepositoryInterface
    {
        Middleware::globalAdminRequired();
        $repo = RepositoryFactory::getAccountResourceRepository();
        if (!$repo->isAvailable()) {
            BaseController::flashError(Translator::translate('resource.msg_no_iredadmin'));
            self::redirect('/dashboard');
        }
        $repo->ensureTablesExist();

        return $repo;
    }

    private static function find(AccountResourceRepositoryInterface $repo, string $id): AccountResource
    {
        $resource = ctype_digit($id) ? $repo->find((int) $id) : null;
        if ($resource === null) {
            BaseController::flashError(Translator::translate('resource.msg_not_found'));
            self::redirect('/account-resources');
        }

        return $resource;
    }

    private static function tab(mixed $tab): string
    {
        return in_array($tab, AccountResourceForm::TABS, true) ? $tab : 'connection';
    }

    private static function redirect(string $location): never
    {
        header("Location: {$location}");
        exit;
    }
}
