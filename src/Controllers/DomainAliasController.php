<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Middleware;
use App\Models\Domain;
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
                $validationErrors = self::validateAlias($alias);

                if (empty($validationErrors)) {
                    RepositoryFactory::getDomainAliasRepository()->createAlias($alias);
                    ActivityLogger::logCreate($alias->targetDomain, '', "Domain alias created: {$alias->aliasDomain} -> {$alias->targetDomain}");
                    BaseController::flashCreated($alias->aliasDomain);
                    header("Location: /domain-aliases");
                    exit;
                }
            } catch (\Exception $e) {
                $error = BaseController::errorMessage($e);
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
     * Returns translated validation errors keyed by form field. An alias domain
     * must not be a mail domain, and its target must be an existing mail domain.
     *
     * @return array<string, string>
     */
    private static function validateAlias(DomainAlias $alias): array
    {
        $errors = [];
        if (empty($alias->aliasDomain)) {
            $errors['aliasDomain'] = Translator::translate('domainalias.msg_alias_required');
        } elseif (!Domain::isValidName($alias->aliasDomain)) {
            $errors['aliasDomain'] = Translator::translate('common.msg_invalid_domain_format');
        } elseif ($alias->aliasDomain === $alias->targetDomain) {
            $errors['aliasDomain'] = Translator::translate('domainalias.msg_same_as_target');
        }
        if (empty($alias->targetDomain)) {
            $errors['targetDomain'] = Translator::translate('domainalias.msg_target_required');
        }
        if (!empty($errors)) {
            return $errors;
        }

        $domainRepo = RepositoryFactory::getDomainRepository();
        if ($domainRepo->getDomain($alias->aliasDomain) !== null) {
            $errors['aliasDomain'] = Translator::translate('domainalias.msg_is_mail_domain', ['alias' => $alias->aliasDomain]);
        } elseif (RepositoryFactory::getDomainAliasRepository()->getAlias($alias->aliasDomain) !== null) {
            $errors['aliasDomain'] = Translator::translate('domainalias.msg_exists', ['alias' => $alias->aliasDomain]);
        }
        if ($domainRepo->getDomain($alias->targetDomain) === null) {
            $errors['targetDomain'] = Translator::translate('domainalias.msg_target_not_found', ['domain' => $alias->targetDomain]);
        }

        return $errors;
    }

    /**
     * Handles alias deletion (POST only).
     */
    public static function aliasDelete(TemplateEngine $tpl, string $aliasDomain): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();

        try {
            $aliasRepo = RepositoryFactory::getDomainAliasRepository();
            $aliasRepo->getAlias($aliasDomain) ?? throw BaseController::itemNotFound();
            $aliasRepo->deleteAlias($aliasDomain);
            ActivityLogger::logDelete('', '', "Domain alias deleted: {$aliasDomain}");
            BaseController::flashDeleted($aliasDomain);
        } catch (\Exception $e) {
            BaseController::flashItemError($aliasDomain, $e);
        }
        header("Location: /domain-aliases");
        exit;
    }
}
