<?php

declare(strict_types=1);

namespace App\Controllers;

use App\CsrfProtection;
use App\I18n\LocaleResolver;
use App\I18n\Translator;
use App\Repositories\RepositoryFactory;
use App\Services\ActivityLogger;
use App\TemplateEngine;

class AuthController
{
    /**
     * Login page request handler.
     */
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
                session_regenerate_id(true);
                $_SESSION['email'] = $email;
                $_SESSION['lastActivity'] = time();
                $_SESSION['loginIp'] = $_SERVER['REMOTE_ADDR'] ?? '';
                $_SESSION['failedLoginAttempts'] = 0;

                // Store RBAC info in session
                $authRepo = RepositoryFactory::getAuthRepository();
                $_SESSION['isGlobalAdmin'] = $authRepo->isGlobalAdmin($email);
                $_SESSION['managedDomains'] = $authRepo->getManagedDomains($email);

                // Apply the admin's stored language preference, if any and supported.
                try {
                    $storedLang = $authRepo->getLanguage($email);
                    if ($storedLang !== '' && Translator::isSupported($storedLang)) {
                        $_SESSION['lang'] = $storedLang;
                        LocaleResolver::persistCookie($storedLang);
                    }
                } catch (\Exception $e) {
                    error_log("Could not load language preference for {$email}: {$e->getMessage()}");
                }

                ActivityLogger::logLogin($email);
                header("Location: $next");
                exit;
            }

            if (!isset($_SESSION['failedLoginAttempts'])) {
                $_SESSION['failedLoginAttempts'] = 0;
            }
            $_SESSION['failedLoginAttempts']++;

            $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            error_log("WARNING: Failed login attempt for '{$email}' from IP {$clientIp} (attempt #{$_SESSION['failedLoginAttempts']})");
            ActivityLogger::log('login', '', $email ?? '', "Login failed from {$clientIp}", 'error');

            $error = 'Invalid credentials!';
        }

        $tpl->render('loginPage.php', [
            'next' => $next,
            'error' => $error,
            'email' => $email,
            'failedAttempts' => $_SESSION['failedLoginAttempts'] ?? 0,
        ]);
    }

    /**
     * Authenticates a user with the given email and password combination.
     */
    private static function authenticateUser(string $email, string $password): bool
    {
        try {
            RepositoryFactory::getAuthRepository()->authenticate($email, $password);
            error_log("User {$email} authenticated successfully");
            return true;
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
                    $authRepo = RepositoryFactory::getAuthRepository();
                    if ($authRepo->supportsLanguagePersistence()) {
                        $authRepo->setLanguage($email, $locale);
                    }
                } catch (\Exception $e) {
                    error_log("Could not persist language for {$email}: {$e->getMessage()}");
                }
            }
        }

        header('Location: ' . self::safeRedirectTarget());
        exit;
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
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        header('Location: /login');
        exit;
    }
}
