<?php

declare(strict_types=1);

namespace App;

use App\Models\Settings;

class Middleware
{
    /**
     * Checks if an admin is authenticated. Redirects to login page if not.
     * Must be called at the top of each protected admin controller method.
     * A self-service session is a mailbox user, never an admin.
     */
    public static function loginRequired(bool $duringTotpSetup = false): void
    {
        self::sessionRequired();

        if (self::isSelfServiceUser()) {
            self::denySelfServiceUser();
        }
        // The panel setting is read again here, so that a global admin who turns
        // the requirement off frees every open session without a new login.
        if (!$duringTotpSetup && !empty($_SESSION['totpSetupRequired'])) {
            if (!Settings::getInstance()->adminTotpRequired) {
                unset($_SESSION['totpSetupRequired']);

                return;
            }
            self::denyUntilTotpSetup();
        }
    }

    /**
     * Holds an admin on the two-factor setup page while the panel requires it.
     */
    private static function denyUntilTotpSetup(): never
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            header('Location: /2fa');
            exit;
        }
        http_response_code(403);
        echo 'Access denied: two-factor authentication setup required';
        exit;
    }

    /**
     * Requires the session of a mailbox user who logged in to self-service.
     */
    public static function selfServiceRequired(): void
    {
        self::sessionRequired();

        if (!self::isSelfServiceUser()) {
            header('Location: /dashboard');
            exit;
        }
    }

    /**
     * Whether the current session belongs to a mailbox user of self-service.
     */
    public static function isSelfServiceUser(): bool
    {
        return !empty($_SESSION['selfService']);
    }

    private static function denySelfServiceUser(): never
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            header('Location: /self');
            exit;
        }
        http_response_code(403);
        echo 'Access denied: admin required';
        exit;
    }

    /**
     * Checks the session of an admin or a self-service user: login, timeout, IP and CSRF.
     */
    private static function sessionRequired(): void
    {
        // A POST-only route answers a GET redirect with 405, so only a GET page is resumed.
        $next = urlencode(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? ($_SERVER['REQUEST_URI'] ?? '/') : '/');
        if (empty($_SESSION['email'])) {
            header("Location: /login?next={$next}");
            exit;
        }

        // Session timeout check
        $settings = Settings::getInstance();
        $lastActivity = $_SESSION['lastActivity'] ?? 0;
        if ($lastActivity > 0 && (time() - $lastActivity) > $settings->sessionTimeout) {
            $_SESSION = [];
            session_destroy();
            header("Location: /login?expired=1&next={$next}");
            exit;
        }
        $_SESSION['lastActivity'] = time();

        self::ipRequired($settings, $next);

        CsrfProtection::validateToken();
    }

    /**
     * Ends the request when the client IP changed since the login, or when it sits
     * outside the configured ranges.
     *
     * @param string $next the urlencoded page to resume after a new login
     */
    private static function ipRequired(Settings $settings, string $next): void
    {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';

        // Session IP change detection
        $loginIp = $_SESSION['loginIp'] ?? '';
        if ($settings->sessionValidateIp && $loginIp !== '' && $clientIp !== $loginIp) {
            $_SESSION = [];
            session_destroy();
            header("Location: /login?ip_changed=1&next={$next}");
            exit;
        }

        // IP restriction check
        if ($settings->allowedIpRanges !== '' && !self::isIpAllowed($clientIp, $settings->allowedIpRanges)) {
            http_response_code(403);
            echo 'Access denied: IP not allowed';
            exit;
        }
    }

    /**
     * Requires global admin role. Returns 403 if not a global admin.
     */
    public static function globalAdminRequired(): void
    {
        self::loginRequired();

        if (!self::isGlobalAdmin()) {
            http_response_code(403);
            echo 'Access denied: global admin required';
            exit;
        }
    }

    /**
     * Requires at least domain admin role for the specified domain.
     */
    public static function domainAdminRequired(string $domain): void
    {
        self::loginRequired();

        if (!self::isGlobalAdmin() && !self::isDomainAdmin($domain)) {
            http_response_code(403);
            echo 'Access denied: not authorized for this domain';
            exit;
        }
    }

    /**
     * Requires a global admin or the admin of at least one domain.
     */
    public static function anyDomainAdminRequired(): void
    {
        self::loginRequired();

        if (!self::isGlobalAdmin() && ($_SESSION['managedDomains'] ?? []) === []) {
            http_response_code(403);
            echo 'Access denied: not authorized for any domain';
            exit;
        }
    }

    /**
     * Whether the current session user is a global admin.
     */
    public static function isGlobalAdmin(): bool
    {
        return !empty($_SESSION['isGlobalAdmin']);
    }

    /**
     * Whether the current session user is a domain admin for the specified domain.
     */
    public static function isDomainAdmin(string $domain): bool
    {
        $managedDomains = $_SESSION['managedDomains'] ?? [];
        return in_array($domain, $managedDomains, true);
    }

    /**
     * Checks if an IP address is in the allowed CIDR ranges.
     */
    public static function isIpAllowed(string $ip, string $ranges): bool
    {
        // An empty entry (e.g. from a trailing comma) is a separator, not a range.
        $rangeList = array_filter(array_map(trim(...), explode(',', $ranges)), fn(string $cidr) => $cidr !== '');
        if ($rangeList === []) {
            return true;
        }
        foreach ($rangeList as $cidr) {
            if (self::ipInCidr($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether an entry of an allowed IP list is an IPv4/IPv6 address or an
     * IPv4 CIDR range with a prefix length of 0 to 32.
     */
    public static function isValidIpRange(string $entry): bool
    {
        if (!str_contains($entry, '/')) {
            return filter_var($entry, FILTER_VALIDATE_IP) !== false;
        }
        [$subnet, $mask] = explode('/', $entry, 2);
        return filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && ctype_digit($mask) && (int) $mask <= 32;
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        if (!self::isValidIpRange($cidr)) {
            return false;
        }
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$subnet, $mask] = explode('/', $cidr, 2);
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return false;
        }
        $shift = 32 - (int) $mask;
        return ($ipLong >> $shift) === (ip2long($subnet) >> $shift);
    }
}
