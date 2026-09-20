<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\Translator;
use App\Middleware;
use App\Models\Settings;
use App\Services\ActivityLogger;
use App\Services\AdminTotp;
use App\Services\RecoveryCodes;
use App\TemplateEngine;
use App\Utils\Totp;

/**
 * Two-factor authentication of an admin: the code page between the password and
 * the session, and the page where an admin sets it up, disables it or writes new
 * recovery codes. A mailbox user of self-service is never asked for a code.
 */
class TwoFactorController
{
    /** A password that is not followed by a code within this time asks for the password again. */
    private const CHALLENGE_TTL = 300;

    /** After this many wrong codes the challenge is dropped and the login starts over. */
    private const MAX_ATTEMPTS = 5;

    /**
     * Holds the verified password of an admin who has two-factor on, and sends
     * the browser to the code page. Returns when the admin has no code to enter.
     */
    public static function startChallenge(string $email, string $next): void
    {
        $totp = self::service();
        if ($totp === null || $totp->status($email)['state'] !== AdminTotp::STATE_ENABLED) {
            return;
        }

        // The session id changes here as well, so a fixed id cannot survive the password step.
        session_regenerate_id(true);
        $_SESSION['totpChallenge'] = ['email' => $email, 'next' => $next, 'at' => time(), 'attempts' => 0];
        header('Location: /login/2fa');
        exit;
    }

    /**
     * Marks the session of an admin who must set two-factor up before using the panel.
     */
    public static function markSetupRequired(string $email): void
    {
        $totp = self::service();
        if (Settings::getInstance()->adminTotpRequired && $totp !== null && $totp->status($email)['state'] !== AdminTotp::STATE_ENABLED) {
            $_SESSION['totpSetupRequired'] = true;
        }
    }

    public static function challenge(TemplateEngine $tpl): void
    {
        $challenge = $_SESSION['totpChallenge'] ?? null;
        if (!is_array($challenge) || time() - (int) $challenge['at'] > self::CHALLENGE_TTL) {
            unset($_SESSION['totpChallenge']);
            header('Location: /login?expired=1');
            exit;
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();
            $error = self::checkChallengeCode($challenge);
        }

        $tpl->render('twoFactorChallenge.php', ['error' => $error, 'email' => (string) $challenge['email']]);
    }

    /**
     * Opens the session when the code fits, and answers the error message otherwise.
     *
     * @param array<string, mixed> $challenge
     */
    private static function checkChallengeCode(array $challenge): string
    {
        $email = (string) $challenge['email'];
        $code = (string) ($_POST['code'] ?? '');
        $totp = AdminTotp::forBackend();

        if ($totp->verifyTotp($email, $code, time())) {
            unset($_SESSION['totpChallenge']);
            AuthController::completeAdminLogin($email, (string) $challenge['next']);
        }
        if ($totp->consumeRecoveryCode($email, $code)) {
            unset($_SESSION['totpChallenge']);
            ActivityLogger::log('login', '', $email, 'Recovery code used, ' . $totp->status($email)['remainingCodes'] . ' left');
            AuthController::completeAdminLogin($email, (string) $challenge['next']);
        }

        return self::rejectCode($email, (int) $challenge['attempts']);
    }

    private static function rejectCode(string $email, int $attempts): string
    {
        $_SESSION['totpChallenge']['attempts'] = $attempts + 1;
        ActivityLogger::log('login', '', $email, 'Two-factor code rejected', 'error');
        if ($attempts + 1 >= self::MAX_ATTEMPTS) {
            unset($_SESSION['totpChallenge']);
            header('Location: /login');
            exit;
        }

        return Translator::translate('totp.msg_invalid_code');
    }

    public static function manage(TemplateEngine $tpl): void
    {
        Middleware::loginRequired(true);
        $admin = (string) ($_SESSION['email'] ?? '');
        $totp = AdminTotp::forBackend();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::applyPost($totp, $admin);
            header('Location: /2fa');
            exit;
        }

        $tpl->render('twoFactor.php', self::viewData($totp, $admin));
    }

    /**
     * @return array<string, mixed>
     */
    private static function viewData(AdminTotp $totp, string $admin): array
    {
        $status = $totp->status($admin);
        $state = $status['state'];
        $pending = $state === AdminTotp::STATE_PENDING;
        $secret = $pending ? (string) $totp->secret($admin) : '';
        $codes = $_SESSION['totpRecoveryCodes'] ?? [];
        unset($_SESSION['totpRecoveryCodes']);

        return [
            'enabled' => $state === AdminTotp::STATE_ENABLED,
            'pending' => $pending,
            'required' => Settings::getInstance()->adminTotpRequired,
            'secret' => $secret,
            'readableSecret' => $secret === '' ? '' : Totp::readable($secret),
            'uri' => $secret === '' ? '' : Totp::uri($secret, $admin, Settings::getInstance()->brandName),
            'recoveryCodes' => $codes,
            'remainingCodes' => $status['remainingCodes'],
        ];
    }

    private static function applyPost(AdminTotp $totp, string $admin): void
    {
        try {
            $message = match ((string) ($_POST['action'] ?? '')) {
                'start' => self::startSetup($totp, $admin),
                'confirm' => self::confirmSetup($totp, $admin),
                'cancel' => self::cancelSetup($totp, $admin),
                'disable' => self::disable($totp, $admin),
                'codes' => self::newRecoveryCodes($totp, $admin),
                default => throw new \RuntimeException(Translator::translate('common.msg_invalid_input')),
            };
            BaseController::flashSuccess($message);
        } catch (\RuntimeException $e) {
            BaseController::flashError($e->getMessage());
        }
    }

    private static function startSetup(AdminTotp $totp, string $admin): string
    {
        $codes = RecoveryCodes::generate();
        $totp->start($admin, Totp::generateSecret(), $codes, time());
        $_SESSION['totpRecoveryCodes'] = $codes;

        return Translator::translate('totp.msg_setup_started');
    }

    private static function confirmSetup(AdminTotp $totp, string $admin): string
    {
        if ($totp->status($admin)['state'] !== AdminTotp::STATE_PENDING) {
            throw new \RuntimeException(Translator::translate('totp.msg_no_pending_setup'));
        }
        if (!$totp->verifyTotp($admin, (string) ($_POST['code'] ?? ''), time())) {
            throw new \RuntimeException(Translator::translate('totp.msg_invalid_code'));
        }

        $totp->confirm($admin);
        unset($_SESSION['totpSetupRequired']);
        ActivityLogger::logUpdate('', $admin, 'Two-factor authentication enabled');

        return Translator::translate('totp.msg_enabled');
    }

    private static function cancelSetup(AdminTotp $totp, string $admin): string
    {
        if ($totp->status($admin)['state'] !== AdminTotp::STATE_PENDING) {
            throw new \RuntimeException(Translator::translate('totp.msg_no_pending_setup'));
        }
        $totp->delete($admin);

        return Translator::translate('totp.msg_setup_cancelled');
    }

    private static function disable(AdminTotp $totp, string $admin): string
    {
        if (Settings::getInstance()->adminTotpRequired) {
            throw new \RuntimeException(Translator::translate('totp.msg_required_by_panel'));
        }
        self::assertCurrentCode($totp, $admin);

        $totp->delete($admin);
        ActivityLogger::logUpdate('', $admin, 'Two-factor authentication disabled');

        return Translator::translate('totp.msg_disabled');
    }

    private static function newRecoveryCodes(AdminTotp $totp, string $admin): string
    {
        self::assertCurrentCode($totp, $admin);

        $codes = RecoveryCodes::generate();
        $totp->replaceRecoveryCodes($admin, $codes);
        $_SESSION['totpRecoveryCodes'] = $codes;
        ActivityLogger::logUpdate('', $admin, 'Two-factor recovery codes replaced');

        return Translator::translate('totp.msg_codes_replaced');
    }

    /**
     * A change of an enabled setup needs a current code, so that an open session
     * of somebody else cannot turn two-factor off.
     *
     * @throws \RuntimeException when two-factor is off or the code does not fit
     */
    private static function assertCurrentCode(AdminTotp $totp, string $admin): void
    {
        if ($totp->status($admin)['state'] !== AdminTotp::STATE_ENABLED) {
            throw new \RuntimeException(Translator::translate('totp.msg_not_enabled'));
        }
        if (!$totp->verifyTotp($admin, (string) ($_POST['code'] ?? ''), time())) {
            throw new \RuntimeException(Translator::translate('totp.msg_invalid_code'));
        }
    }

    /**
     * The service, or null when the panel has no iredadmin database: no admin can
     * hold a two-factor secret then. A database that IS configured but unreachable
     * throws, so an outage never lets an admin past the code page.
     *
     * @throws \App\Exceptions\BackendConnectionException when the configured database is not reachable
     */
    private static function service(): ?AdminTotp
    {
        if (Settings::getInstance()->iredadminDbHost === '') {
            return null;
        }
        return AdminTotp::forBackend();
    }
}
