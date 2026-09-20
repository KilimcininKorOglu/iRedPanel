<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\Exceptions\MailDeliveryException;
use App\I18n\Translator;
use App\Models\DomainSettings;
use App\Models\Settings;
use App\Models\User;
use App\Models\UserPassword;
use App\Repositories\RepositoryFactory;
use App\Services\ActivityLogger;
use App\Services\Mailer;
use App\Services\PasswordResets;
use App\TemplateEngine;
use App\Utils\PasswordUtils;

/**
 * Public password recovery pages. A mailbox user asks for a reset link, the panel
 * mails it to the recovery address of the mailbox, and the link opens a form that
 * sets a new password. The pages answer the same way for a known and an unknown
 * address, so they never tell a visitor whether a mailbox exists.
 */
class PasswordResetController
{
    public static function request(TemplateEngine $tpl): void
    {
        if (!self::enabled($tpl)) {
            return;
        }

        $sent = false;
        $email = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            self::start($email);
            $sent = true;
        }

        $tpl->render('passwordForgot.php', [
            'sent' => $sent,
            'email' => $sent ? '' : $email,
            'expireHours' => PasswordResets::EXPIRE_HOURS,
        ]);
    }

    public static function reset(TemplateEngine $tpl, string $token): void
    {
        if (!self::enabled($tpl)) {
            return;
        }

        $resets = PasswordResets::forBackend();
        $resets->ensureTableExists();
        $email = $resets->find($token, time());
        if ($email === null) {
            http_response_code(404);
            $tpl->render('newsletterError.php', ['message' => Translator::translate('recovery.msg_invalid_token')]);
            return;
        }

        $error = null;
        $done = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();
            try {
                self::applyPassword($email, $resets);
                $done = true;
            } catch (\RuntimeException $e) {
                $error = $e->getMessage();
            }
        }

        $tpl->render('passwordReset.php', [
            'token' => $token,
            'email' => $email,
            'error' => $error,
            'done' => $done,
        ]);
    }

    /**
     * Stores the request and mails the reset link. A mailbox that cannot be
     * recovered produces no mail, and the page answer stays the same.
     */
    private static function start(string $email): void
    {
        try {
            self::issueLink($email);
        } catch (\Throwable $e) {
            // The visitor gets the same answer either way, so the cause goes to the log.
            error_log('Password reset request failed: ' . $e->getMessage());
        }
    }

    /**
     * @throws \Throwable when the request cannot be stored or the mail cannot be sent
     */
    private static function issueLink(string $email): void
    {
        $user = self::recoverableUser($email);
        if ($user === null) {
            return;
        }
        $resets = PasswordResets::forBackend();
        $resets->ensureTableExists();
        $token = $resets->create($email, time());
        if ($token === null) {
            // A link of this mailbox was just sent.
            return;
        }

        try {
            self::sendLink($email, $user->recoveryEmail, $token);
        } catch (\Throwable $e) {
            // The link never reached the user, so the next attempt must not wait.
            $resets->deleteForEmail($email);
            throw $e;
        }
    }

    /**
     * The mailbox that may be recovered: it is active, its domain is active, and it
     * carries a recovery address.
     */
    private static function recoverableUser(string $email): ?User
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }
        [$uid, $domainName] = explode('@', $email, 2);

        $domain = RepositoryFactory::getDomainRepository()->getDomain($domainName);
        if ($domain === null || !$domain->active) {
            return null;
        }
        $user = RepositoryFactory::getUserRepository()->getUser($domainName, $uid);
        if ($user === null || !$user->accountStatus || $user->recoveryEmail === '') {
            return null;
        }

        return $user;
    }

    /**
     * @throws MailDeliveryException when the public URL or SMTP is not configured, or delivery fails
     */
    private static function sendLink(string $email, string $recoveryEmail, string $token): void
    {
        $settings = Settings::getInstance();
        if ($settings->publicUrl === '') {
            throw new MailDeliveryException('IREDPANEL_PUBLIC_URL is not set');
        }

        $params = [
            'email' => $email,
            'link' => "{$settings->publicUrl}/reset-password/{$token}",
            'hours' => PasswordResets::EXPIRE_HOURS,
        ];

        Mailer::fromSettings()->send(
            $recoveryEmail,
            Translator::translate('recovery.mail_subject', $params),
            Translator::translate('recovery.mail_body', $params),
        );
    }

    /**
     * @throws \RuntimeException when the password does not pass the policy
     */
    private static function applyPassword(string $email, PasswordResets $resets): void
    {
        [$uid, $domainName] = explode('@', $email, 2);
        $password = (string) ($_POST['password'] ?? '');
        $errors = UserPassword::validateLocalized($password, (string) ($_POST['password_repeat'] ?? ''), self::domainSettings($domainName));
        if ($errors !== []) {
            throw new \RuntimeException(implode(' ', $errors));
        }

        RepositoryFactory::getUserRepository()
            ->updateUserPassword($domainName, $uid, PasswordUtils::generatePasswordHash($password));
        $resets->deleteForEmail($email);
        ActivityLogger::logUpdate($domainName, $uid, 'Password reset through the recovery address');
    }

    private static function domainSettings(string $domainName): DomainSettings
    {
        $domain = RepositoryFactory::getDomainRepository()->getDomain($domainName);

        return DomainSettings::fromSettingsString($domain->settings ?? '');
    }

    /**
     * Renders the 404 page and answers false when password recovery is off.
     */
    private static function enabled(TemplateEngine $tpl): bool
    {
        if (Settings::getInstance()->passwordRecoveryEnabled) {
            return true;
        }
        http_response_code(404);
        $tpl->render('newsletterError.php', ['message' => Translator::translate('recovery.msg_disabled')]);

        return false;
    }
}
