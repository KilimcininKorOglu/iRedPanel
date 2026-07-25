<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Middleware;
use App\Models\DomainAlias;
use App\Models\Settings;
use App\Repositories\RepositoryFactory;
use App\Services\ActivityLogger;
use App\TemplateEngine;

class DomainAliasController
{
    /**
     * Displays the paginated domain alias list page.
     */
    public static function aliasList(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();

        $settings = Settings::getInstance();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $settings->paginationPerPage;

        $paginatedResult = RepositoryFactory::getDomainAliasRepository()
            ->getAllAliasesPaginated($page, $perPage);

        $tpl->render('domainAliasList.php', [
            'aliases' => $paginatedResult->items,
            'paginatedResult' => $paginatedResult,
        ]);
    }

    /**
     * Displays the alias creation form and handles creation.
     */
    public static function aliasCreate(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();

        $error = null;
        $validationErrors = [];
        $alias = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();

            try {
                $alias = DomainAlias::fromFormData($_POST);

                if (empty($alias->aliasDomain)) {
                    $validationErrors['aliasDomain'] = Translator::translate('domainalias.msg_alias_required');
                } elseif (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}$/', $alias->aliasDomain)) {
                    $validationErrors['aliasDomain'] = Translator::translate('common.msg_invalid_domain_format');
                }

                if (empty($alias->targetDomain)) {
                    $validationErrors['targetDomain'] = Translator::translate('domainalias.msg_target_required');
                }

                if ($alias->aliasDomain === $alias->targetDomain) {
                    $validationErrors['aliasDomain'] = Translator::translate('domainalias.msg_same_as_target');
                }

                if (empty($validationErrors)) {
                    $repo = RepositoryFactory::getDomainAliasRepository();
                    if ($repo->getAlias($alias->aliasDomain) !== null) {
                        $validationErrors['aliasDomain'] = Translator::translate('domainalias.msg_exists', ['alias' => $alias->aliasDomain]);
                    } else {
                        $repo->createAlias($alias);
                        ActivityLogger::logCreate($alias->targetDomain, '', "Domain alias created: {$alias->aliasDomain} -> {$alias->targetDomain}");
                        header("Location: /domain-aliases");
                        exit;
                    }
                }
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        $allDomains = RepositoryFactory::getDomainRepository()->getDomains();

        $tpl->render('domainAliasCreate.php', [
            'alias' => $alias,
            'error' => $error,
            'validationErrors' => $validationErrors,
            'allDomains' => $allDomains,
        ]);
    }

    /**
     * Handles alias deletion (POST only).
     */
    public static function aliasDelete(TemplateEngine $tpl, string $aliasDomain): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();

        try {
            RepositoryFactory::getDomainAliasRepository()->deleteAlias($aliasDomain);
            ActivityLogger::logDelete('', '', "Domain alias deleted: {$aliasDomain}");
            header("Location: /domain-aliases");
            exit;
        } catch (\Exception $e) {
            http_response_code(500);
            $tpl->render('page404.php');
        }
    }
}
