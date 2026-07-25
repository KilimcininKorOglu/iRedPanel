<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Middleware;
use App\Models\Domain;
use App\Models\DomainSettings;
use App\Models\Settings;
use App\Repositories\RepositoryFactory;
use App\Services\ActivityLogger;
use App\TemplateEngine;

class DomainController
{
    /**
     * Displays the paginated domain list page.
     */
    public static function domainList(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();

        $settings = Settings::getInstance();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = $settings->paginationPerPage;
        $statusFilter = $_GET['status'] ?? null;
        $activeOnly = match ($statusFilter) {
            'active' => true,
            'disabled' => false,
            default => null,
        };

        $paginatedResult = RepositoryFactory::getDomainRepository()->getDomainsPaginated($page, $perPage, $activeOnly);

        $tpl->render('domainList.php', [
            'paginatedResult' => $paginatedResult,
            'domains' => $paginatedResult->items,
            'statusFilter' => $statusFilter,
        ]);
    }

    /**
     * Displays the domain creation form and handles creation.
     */
    public static function domainCreate(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();

        $error = null;
        $validationErrors = [];
        $domain = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $domain = Domain::fromFormData($_POST);

                if (empty($domain->domainName)) {
                    $validationErrors['domainName'] = Translator::translate('domain.msg_name_required');
                } elseif (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}$/', $domain->domainName)) {
                    $validationErrors['domainName'] = Translator::translate('common.msg_invalid_domain_format');
                }

                if (empty($validationErrors)) {
                    $repo = RepositoryFactory::getDomainRepository();

                    // Check for duplicate
                    if ($repo->getDomain($domain->domainName) !== null) {
                        $validationErrors['domainName'] = Translator::translate('domain.msg_exists', ['domain' => $domain->domainName]);
                    } elseif (Settings::getInstance()->requireDomainOwnershipVerification) {
                        $ownershipRepo = RepositoryFactory::getDomainOwnershipRepository();
                        if (!$ownershipRepo->isVerified($domain->domainName)) {
                            $validationErrors['domainName'] = Translator::translate('domain.msg_ownership_required');
                        }
                    }

                    // Enforce admin domain creation limit with transaction to prevent TOCTOU
                    if (empty($validationErrors)) {
                        $pdo = self::getBackendPdo();
                        if ($pdo !== null) {
                            $pdo->beginTransaction();
                        }
                        try {
                            $adminEmail = $_SESSION['email'] ?? '';
                            $adminRepo = RepositoryFactory::getAdminRepository();
                            $admin = $adminRepo->getAdmin($adminEmail);
                            if ($admin !== null && $admin->createMaxDomains >= 0) {
                                $counts = $adminRepo->getAdminResourceCounts($adminEmail);
                                if ($counts['domains'] >= $admin->createMaxDomains) {
                                    $validationErrors['domainName'] = Translator::translate('domain.msg_creation_limit', ['limit' => $admin->createMaxDomains]);
                                }
                            }

                            if (empty($validationErrors)) {
                                $repo->createDomain($domain);
                                if ($pdo !== null) {
                                    $pdo->commit();
                                }
                                ActivityLogger::logCreate($domain->domainName, '', "Domain created: {$domain->domainName}");
                                header("Location: /domains");
                                exit;
                            }

                            if ($pdo !== null && $pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                        } catch (\Throwable $e) {
                            if ($pdo !== null && $pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            throw $e;
                        }
                    }
                }
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        $tpl->render('domainCreate.php', [
            'domain' => $domain,
            'error' => $error,
            'validationErrors' => $validationErrors,
        ]);
    }

    /**
     * Displays and handles domain editing.
     */
    public static function domainView(TemplateEngine $tpl, string $domainName, string $editMode = 'general'): void
    {
        Middleware::globalAdminRequired();

        if (!in_array($editMode, ['general', 'settings', 'catchall', 'bcc', 'relay'], true)) {
            http_response_code(404);
            $tpl->render('page404.php');
            return;
        }

        $repo = RepositoryFactory::getDomainRepository();
        $error = null;
        $success = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                if ($editMode === 'general') {
                    $formDomain = Domain::fromFormData($_POST);
                    $domain = new Domain(
                        domainName: $domainName,
                        description: $formDomain->description,
                        active: $formDomain->active,
                        maxQuota: $formDomain->maxQuota,
                        quota: $formDomain->quota,
                        mailboxes: $formDomain->mailboxes,
                        aliases: $formDomain->aliases,
                        transport: $formDomain->transport,
                    );
                    $repo->updateDomain($domain);
                    ActivityLogger::logUpdate($domainName, '', "Domain updated: {$domainName}");
                    $success = Translator::translate('domain.msg_updated');
                } elseif ($editMode === 'settings') {
                    $domainSettings = DomainSettings::fromFormData($_POST);
                    $currentDomain = $repo->getDomain($domainName);
                    if ($currentDomain !== null) {
                        $currentDomain->settings = $domainSettings->toSettingsString();
                        $repo->updateDomain($currentDomain);
                        ActivityLogger::logUpdate($domainName, '', "Domain settings updated: {$domainName}");
                        $success = Translator::translate('domain.msg_settings_updated');
                    }
                } elseif ($editMode === 'catchall') {
                    CsrfProtection::validateToken();
                    $catchallTarget = trim($_POST['catchallTarget'] ?? '');
                    $aliasRepo = RepositoryFactory::getAliasRepository();
                    $aliasRepo->setCatchall($domainName, $catchallTarget !== '' ? $catchallTarget : null);
                    ActivityLogger::logUpdate($domainName, '', "Catch-all updated: {$domainName}");
                    $success = Translator::translate('domain.msg_catchall_updated');
                } elseif ($editMode === 'bcc') {
                    CsrfProtection::validateToken();
                    $bccRepo = RepositoryFactory::getBccRepository();
                    $senderBcc = trim($_POST['senderBcc'] ?? '');
                    $recipientBcc = trim($_POST['recipientBcc'] ?? '');
                    $bccRepo->setDomainSenderBcc($domainName, $senderBcc !== '' ? $senderBcc : null);
                    $bccRepo->setDomainRecipientBcc($domainName, $recipientBcc !== '' ? $recipientBcc : null);
                    ActivityLogger::logUpdate($domainName, '', "BCC settings updated: {$domainName}");
                    $success = Translator::translate('common.msg_bcc_updated');
                } elseif ($editMode === 'relay') {
                    CsrfProtection::validateToken();
                    $relayRepo = RepositoryFactory::getRelayRepository();
                    $relayhost = trim($_POST['relayhost'] ?? '');
                    $relayRepo->setRelayhost('@' . $domainName, $relayhost !== '' ? $relayhost : null);
                    ActivityLogger::logUpdate($domainName, '', "Relay settings updated: {$domainName}");
                    $success = Translator::translate('common.msg_relay_updated');
                }
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        $domain = $repo->getDomain($domainName);
        if ($domain === null) {
            http_response_code(404);
            $tpl->render('page404.php');
            return;
        }

        $domainSettings = DomainSettings::fromSettingsString($domain->settings);

        $catchallTarget = null;
        if ($editMode === 'catchall') {
            $catchallTarget = RepositoryFactory::getAliasRepository()->getCatchall($domainName);
        }

        $senderBcc = null;
        $recipientBcc = null;
        if ($editMode === 'bcc') {
            $bccRepo = RepositoryFactory::getBccRepository();
            $senderBcc = $bccRepo->getDomainSenderBcc($domainName);
            $recipientBcc = $bccRepo->getDomainRecipientBcc($domainName);
        }

        $domainRelayhost = null;
        if ($editMode === 'relay') {
            $domainRelayhost = RepositoryFactory::getRelayRepository()->getRelayhost('@' . $domainName);
        }

        $tpl->render('domainView.php', [
            'domain' => $domain,
            'domainSettings' => $domainSettings,
            'editMode' => $editMode,
            'catchallTarget' => $catchallTarget,
            'senderBcc' => $senderBcc,
            'recipientBcc' => $recipientBcc,
            'domainRelayhost' => $domainRelayhost,
            'error' => $error,
            'success' => $success,
        ]);
    }

    /**
     * Handles bulk domain operations (POST only).
     */
    public static function bulkAction(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();

        $selectedDomains = $_POST['selectedDomains'] ?? [];
        $action = $_POST['action'] ?? '';
        $adminEmail = $_SESSION['email'] ?? '';

        if (empty($selectedDomains) || !is_array($selectedDomains)) {
            header("Location: /domains");
            exit;
        }

        $domainRepo = RepositoryFactory::getDomainRepository();

        foreach ($selectedDomains as $domainName) {
            try {
                if ($action === 'enable') {
                    $domainRepo->enableDisableDomain($domainName, true);
                } elseif ($action === 'disable') {
                    $domainRepo->enableDisableDomain($domainName, false);
                } elseif ($action === 'delete') {
                    $domainRepo->deleteDomain($domainName, $adminEmail);
                }
            } catch (\Exception $e) {
                error_log("Bulk action '{$action}' failed for domain '{$domainName}': " . $e->getMessage());
            }
        }

        ActivityLogger::log($action, '', '', "Bulk {$action} on " . count($selectedDomains) . " domains");
        header("Location: /domains");
        exit;
    }

    /**
     * Handles domain deletion (POST only).
     */
    public static function domainDelete(TemplateEngine $tpl, string $domainName): void
    {
        Middleware::globalAdminRequired();
        CsrfProtection::validateToken();

        try {
            $adminEmail = $_SESSION['email'] ?? '';
            RepositoryFactory::getDomainRepository()->deleteDomain($domainName, $adminEmail);
            ActivityLogger::logDelete($domainName, '', "Domain deleted: {$domainName}");
            header("Location: /domains");
            exit;
        } catch (\Exception $e) {
            http_response_code(500);
            $tpl->render('page404.php');
        }
    }

    private static function getBackendPdo(): ?\PDO
    {
        return match (Settings::getInstance()->backend) {
            'mysql' => \App\Repositories\Mysql\MysqlConnection::getInstance()->getPdo(),
            'pgsql' => \App\Repositories\Pgsql\PgsqlConnection::getInstance()->getPdo(),
            default => null,
        };
    }
}
