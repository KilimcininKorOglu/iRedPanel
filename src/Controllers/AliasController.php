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
                $membersRaw = trim($_POST['members'] ?? '');

                if ($localPart === '' || $domain === '') {
                    throw new \RuntimeException('Email address and domain are required');
                }

                $address = $localPart . '@' . $domain;
                $members = array_filter(array_map('trim', explode("\n", $membersRaw)));

                $repo = RepositoryFactory::getAliasRepository();

                if ($repo->getAlias($address) !== null) {
                    throw new \RuntimeException('Alias already exists: ' . $address);
                }

                // Enforce domain alias limit
                $domainObj = RepositoryFactory::getDomainRepository()->getDomain($domain);
                if ($domainObj !== null && $domainObj->aliases > 0) {
                    $aliasCount = $repo->countAliasesForDomain($domain);
                    if ($aliasCount >= $domainObj->aliases) {
                        throw new \RuntimeException("Domain alias limit reached ({$aliasCount}/{$domainObj->aliases})");
                    }
                }

                $repo->createAlias($address, $domain, $name, $members, $accessPolicy);
                ActivityLogger::logCreate('alias', $domain, "Created mail alias: {$address}");

                header("Location: /aliases/{$address}");
                exit;
            } catch (\Exception $e) {
                $error = $e->getMessage();
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
                    $newMember = trim($_POST['newMember'] ?? '');
                    if ($newMember !== '') {
                        $repo->addAliasMember($address, $newMember);
                        ActivityLogger::logUpdate('alias', $alias->domain, "Added member {$newMember} to alias {$address}");
                    }
                } elseif ($action === 'removeMember') {
                    $memberToRemove = $_POST['member'] ?? '';
                    if ($memberToRemove !== '') {
                        $repo->removeAliasMember($address, $memberToRemove);
                        ActivityLogger::logUpdate('alias', $alias->domain, "Removed member {$memberToRemove} from alias {$address}");
                    }
                } elseif ($action === 'updateSettings') {
                    $name = trim($_POST['name'] ?? '');
                    $accessPolicy = trim($_POST['accessPolicy'] ?? 'public');
                    $active = isset($_POST['active']);
                    $membersRaw = trim($_POST['members'] ?? '');
                    $updatedMembers = array_filter(array_map('trim', explode("\n", $membersRaw)));

                    $repo->updateAlias($address, $name, $updatedMembers, $accessPolicy, $active);
                    ActivityLogger::logUpdate('alias', $alias->domain, "Updated alias settings: {$address}");
                } elseif ($action === 'updateModerators') {
                    $moderatorsRaw = trim($_POST['moderators'] ?? '');
                    $newModerators = array_filter(array_map('trim', explode("\n", $moderatorsRaw)));

                    $repo->setModerators($address, $newModerators);
                    ActivityLogger::logUpdate('alias', $alias->domain, "Updated moderators for alias {$address}");
                }

                $success = Translator::translate('alias.msg_updated');
                $alias = $repo->getAlias($address);
                $members = $repo->getAliasMembers($address);
                $moderators = $repo->getModerators($address);
            } catch (\Exception $e) {
                $error = $e->getMessage();
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
        $alias = $repo->getAlias($address);

        if ($alias !== null) {
            $repo->deleteAlias($address);
            ActivityLogger::logDelete('alias', $alias->domain, "Deleted mail alias: {$address}");
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

        if (empty($selectedAliases)) {
            header("Location: /aliases");
            exit;
        }

        $repo = RepositoryFactory::getAliasRepository();

        foreach ($selectedAliases as $address) {
            if ($action === 'enable') {
                $repo->enableDisableAlias($address, true);
                ActivityLogger::logUpdate('alias', '', "Enabled alias: {$address}");
            } elseif ($action === 'disable') {
                $repo->enableDisableAlias($address, false);
                ActivityLogger::logUpdate('alias', '', "Disabled alias: {$address}");
            } elseif ($action === 'delete') {
                $repo->deleteAlias($address);
                ActivityLogger::logDelete('alias', '', "Deleted alias: {$address}");
            }
        }

        header("Location: /aliases");
        exit;
    }
}
