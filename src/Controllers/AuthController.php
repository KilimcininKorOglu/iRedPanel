<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\Exceptions\BackendConnectionException;
use App\I18n\LocaleResolver;
use App\I18n\Translator;
use App\Middleware;
use App\Repositories\RepositoryFactory;
use App\Services\ActivityLogger;
use App\Services\SelfService;
use App\TemplateEngine;

class AuthController
{
    /**
     * Login page request handler.
     */
    /**
     * Explains why Middleware::loginRequired() closed the previous session.
     */
    private static function loginNotice(): ?string
    {
        return match (true) {
            isset($_GET['expired']) => Translator::translate('auth.msg_session_expired'),
            isset($_GET['ip_changed']) => Translator::translate('auth.msg_ip_changed'),
            default => null,
        };
    }

    public static function loginPage(TemplateEngine $tpl): void
    {
        $next = $_GET['next'] ?? $_POST['next'] ?? '/';
        if (!str_starts_with($next, '/') || str_starts_with($next, '//') || str_starts_with($next, '/\\')) {
            $next = '/';
        }
        $error = null;
        $email = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            CsrfProtection::validateToken();
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';

            if (self::authenticateUser($email, $password)) {
                TwoFactorController::startChallenge($email, $next);
                self::completeAdminLogin($email, $next);
            }

            // A mailbox that is no admin logs in to self-service when its domain allows it.
            if (SelfService::authenticate($email, $password)) {
                $email = strtolower(trim($email));
                self::startSession($email);
                $_SESSION['selfService'] = true;
                self::applyStoredLanguage($email, static fn (): string => SelfService::user()->language ?? '');

                ActivityLogger::log('user_login', '', $email, 'User login (self-service)');
                header('Location: ' . (str_starts_with($next, '/self') ? $next : '/self'));
                exit;
            }

            $_SESSION['failedLoginAttempts'] ??= 0;
            $_SESSION['failedLoginAttempts']++;

            $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            error_log("WARNING: Failed login attempt for '{$email}' from IP {$clientIp} (attempt #{$_SESSION['failedLoginAttempts']})");
            ActivityLogger::log('login', '', $email, "Login failed from {$clientIp}", 'error');

            $error = Translator::translate('auth.invalid_credentials');
        }

        $tpl->render('loginPage.php', [
            'next' => $next,
            'error' => $error,
            'notice' => $error === null ? self::loginNotice() : null,
            'email' => $email,
            'failedAttempts' => $_SESSION['failedLoginAttempts'] ?? 0,
        ]);
    }

    /**
     * Opens the admin session and sends the browser to the requested page. The
     * caller has verified the password and, when the admin has it on, the
     * two-factor code.
     */
    public static function completeAdminLogin(string $email, string $next): never
    {
        self::startSession($email);
        $authRepo = RepositoryFactory::getAuthRepository();
        $_SESSION['isGlobalAdmin'] = $authRepo->isGlobalAdmin($email);
        $_SESSION['managedDomains'] = $authRepo->getManagedDomains($email);
        self::applyStoredLanguage($email, static fn (): string => $authRepo->getLanguage($email));
        TwoFactorController::markSetupRequired($email);

        ActivityLogger::logLogin($email);
        header('Location: ' . (empty($_SESSION['totpSetupRequired']) ? $next : '/2fa'));
        exit;
    }

    /**
     * Starts a new session after a successful login. The role fields are set by the caller.
     */
    private static function startSession(string $email): void
    {
        session_regenerate_id(true);
        $_SESSION['email'] = $email;
        $_SESSION['lastActivity'] = time();
        $_SESSION['loginIp'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['failedLoginAttempts'] = 0;
        $_SESSION['isGlobalAdmin'] = false;
        $_SESSION['managedDomains'] = [];
        unset($_SESSION['selfService'], $_SESSION['totpChallenge'], $_SESSION['totpSetupRequired'], $_SESSION['totpRecoveryCodes']);
    }

    /**
     * Applies the stored language preference of the account, if any and supported.
     *
     * @param callable(): string $read
     */
    private static function applyStoredLanguage(string $email, callable $read): void
    {
        try {
            $storedLang = $read();
            if ($storedLang !== '' && Translator::isSupported($storedLang)) {
                $_SESSION['lang'] = $storedLang;
                LocaleResolver::persistCookie($storedLang);
            }
        } catch (\Exception $e) {
            error_log("Could not load language preference for {$email}: {$e->getMessage()}");
        }
    }

    /**
     * Authenticates a user with the given email and password combination.
     *
     * @throws BackendConnectionException if the backend is not reachable, so
     *         the outage is not reported as invalid credentials
     */
    private static function authenticateUser(string $email, string $password): bool
    {
        try {
            RepositoryFactory::getAuthRepository()->authenticate($email, $password);
            error_log("User {$email} authenticated successfully");
            return true;
        } catch (BackendConnectionException $e) {
            throw $e;
        } catch (\Exception $e) {
            error_log("Failed to authenticate user {$email}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Language switch handler. Validates the requested locale against the
     * whitelist, stores it in session + cookie, persists it to the backend
     * when logged in, and redirects back to a safe internal page.
     */
    public static function changeLanguage(): void
    {
        CsrfProtection::validateToken();

        $locale = $_POST['locale'] ?? '';
        if (Translator::isSupported($locale)) {
            $_SESSION['lang'] = $locale;
            LocaleResolver::persistCookie($locale);

            $email = $_SESSION['email'] ?? '';
            if ($email !== '') {
                try {
                    self::persistLanguage($email, $locale);
                } catch (\Exception $e) {
                    error_log("Could not persist language for {$email}: {$e->getMessage()}");
                }
            }
        }

        header('Location: ' . self::safeRedirectTarget());
        exit;
    }

    /**
     * Stores the language of the logged-in admin, or of the mailbox of a self-service user.
     */
    private static function persistLanguage(string $email, string $locale): void
    {
        if (Middleware::isSelfServiceUser()) {
            $account = SelfService::account();
            $user = SelfService::user() ?? throw new \RuntimeException("Mailbox '{$email}' not found");
            $user->language = $locale;
            RepositoryFactory::getUserRepository()->updateUser($account['domain'], $user);
            return;
        }

        $authRepo = RepositoryFactory::getAuthRepository();
        if ($authRepo->supportsLanguagePersistence()) {
            $authRepo->setLanguage($email, $locale);
        }
    }

    /**
     * Returns a safe same-origin redirect target from the Referer header,
     * defaulting to '/'. Rejects absolute/scheme-relative URLs.
     */
    private static function safeRedirectTarget(): string
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $path = parse_url($referer, PHP_URL_PATH);
        if (is_string($path) && str_starts_with($path, '/') && !str_starts_with($path, '//')) {
            $query = parse_url($referer, PHP_URL_QUERY);
            return $query ? "{$path}?{$query}" : $path;
        }
        return '/';
    }

    /**
     * Logout request handler.
     */
    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                ['expires' => time() - 42000, 'path' => $params['path'], 'domain' => $params['domain'], 'secure' => $params['secure'], 'httponly' => $params['httponly']]
            );
        }
        session_destroy();
        header('Location: /login');
        exit;
    }
}
