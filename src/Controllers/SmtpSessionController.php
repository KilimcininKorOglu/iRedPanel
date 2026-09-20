<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Middleware;
use App\Models\Settings;
use App\Repositories\RepositoryFactory;
use App\Services\ActivityLogger;
use App\Services\WhiteBlacklistService;
use App\TemplateEngine;

/**
 * The SMTP sessions that iRedAPD recorded, and the white/blacklist entries that
 * one session produces. The session table itself is never written here.
 */
class SmtpSessionController
{
    /**
     * The explanation of one action value, or an empty string when the panel has no
     * text for it. The key follows the action in lower case, so an action that
     * iRedAPD adds later shows the bare value instead of a wrong text.
     */
    public static function actionHelp(string $action): string
    {
        $key = 'smtpsession.action_' . strtolower($action);

        return Translator::has($key) ? Translator::translate($key) : '';
    }

    /**
     * The SMTP sessions that iRedAPD recorded. The table is read only here: iRedAPD
     * writes it while `LOG_SMTP_SESSIONS` is on and its own job removes the old rows.
     */
    public static function smtpSessions(TemplateEngine $tpl): void
    {
        Middleware::globalAdminRequired();
        IredapdController::requireEnabled();

        [$action, $email, $ip] = self::smtpSessionFilters();
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $repo = RepositoryFactory::getIredapdRepository();
        $paginatedResult = $repo->getSmtpSessionsPaginated(
            $page,
            Settings::getInstance()->paginationPerPage,
            $action,
            $email,
            $ip,
        );

        $tpl->render('smtpSessions.php', [
            'sessions' => $paginatedResult->items,
            'paginatedResult' => $paginatedResult,
            'actions' => $repo->getSmtpSessionActions(),
            'actionHelp' => self::actionHelp(...),
            'filterAction' => $action ?? '',
            'filterEmail' => $email ?? '',
            'filterIp' => $ip ?? '',
            // Only an empty page needs the answer, so a busy table costs no extra query.
            'available' => $paginatedResult->totalCount > 0 || $repo->smtpSessionsAvailable(),
        ]);
    }

    public static function smtpSession(TemplateEngine $tpl, string $id): void
    {
        Middleware::globalAdminRequired();
        IredapdController::requireEnabled();

        $session = RepositoryFactory::getIredapdRepository()->getSmtpSession((int) $id);
        if ($session === null) {
            BaseController::page404($tpl);
            return;
        }

        // Named smtpSession, because every template already holds the login session as $session.
        $tpl->render('smtpSessionView.php', [
            'smtpSession' => $session,
            'actionHelp' => self::actionHelp(...),
        ]);
    }

    /**
     * Adds the sender or the reverse DNS name of one session to a white or black list
     * (POST only). The values come from the stored session, never from the form.
     */
    public static function wblistFromSession(TemplateEngine $tpl, string $id): void
    {
        Middleware::globalAdminRequired();
        IredapdController::requireEnabled();
        CsrfProtection::validateToken();

        $session = RepositoryFactory::getIredapdRepository()->getSmtpSession((int) $id);
        if ($session === null) {
            BaseController::flashError(Translator::translate('common.msg_item_not_found'));
            header('Location: ' . BaseController::listUrl('/iredapd/smtp-sessions'));
            exit;
        }

        $wb = ($_POST['wb'] ?? 'B') === 'W' ? 'W' : 'B';
        if (($_POST['target'] ?? '') === 'rdns') {
            self::storeSessionRdns((string) $session['reverse_client_name'], $wb);
        } else {
            self::storeSessionSender((string) $session['sender'], (string) $session['recipient'], $wb);
        }

        header('Location: ' . BaseController::listUrl('/iredapd/smtp-sessions'));
        exit;
    }

    /**
     * Writes the sender into the inbound list of the recipient. A recipient outside the
     * served domains has no own list, so the entry goes to the global account.
     */
    private static function storeSessionSender(string $sender, string $recipient, string $wb): void
    {
        if ($sender === '') {
            BaseController::flashError(Translator::translate('common.msg_item_not_found'));
            return;
        }

        $account = self::wblistAccount($recipient);
        try {
            WhiteBlacklistService::add($account, [$sender], $wb, 'inbound');
            ActivityLogger::log('update', '', $account, "Added {$wb} entry for {$sender} from an SMTP session");
            BaseController::flashSuccess(
                Translator::translate('smtpsession.msg_sender_listed', ['account' => $account])
            );
        } catch (\Exception $e) {
            BaseController::flashItemError($sender, $e);
        }
    }

    private static function storeSessionRdns(string $rdns, string $wb): void
    {
        if ($rdns === '') {
            BaseController::flashError(Translator::translate('common.msg_item_not_found'));
            return;
        }

        try {
            $added = RepositoryFactory::getIredapdRepository()->addWblistRdns($rdns, $wb);
            if (!$added) {
                BaseController::flashError(Translator::translate('smtpsession.msg_rdns_exists', ['rdns' => $rdns]));
                return;
            }
            ActivityLogger::log('update', '', $rdns, "Added {$wb} rDNS entry from an SMTP session");
            BaseController::flashSuccess(Translator::translate('smtpsession.msg_rdns_listed', ['rdns' => $rdns]));
        } catch (\Exception $e) {
            BaseController::flashItemError($rdns, $e);
        }
    }

    /**
     * The account whose list carries the entry: the recipient when the panel serves its
     * domain, otherwise the global account.
     */
    private static function wblistAccount(string $recipient): string
    {
        $domain = strtolower(substr(strrchr($recipient, '@') ?: '@', 1));
        if ($domain === '') {
            return '@.';
        }

        return RepositoryFactory::getDomainRepository()->getDomain($domain) === null ? '@.' : strtolower($recipient);
    }

    /**
     * The three list filters of the SMTP session page, empty values as null.
     *
     * @return array{0: ?string, 1: ?string, 2: ?string} action, email and client address
     */
    private static function smtpSessionFilters(): array
    {
        $read = static function (string $name): ?string {
            $value = is_string($_GET[$name] ?? null) ? trim($_GET[$name]) : '';

            return $value === '' ? null : $value;
        };

        return [$read('action'), $read('email'), $read('ip')];
    }
}
