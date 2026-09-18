<?php

declare(strict_types=1);

namespace App;

use App\Models\Settings;

class Middleware
{
    /**
     * Checks if user is authenticated. Redirects to login page if not.
     * Must be called at the top of each protected controller method.
     */
    public static function loginRequired(): void
    {
        if (empty($_SESSION['email'])) {
            $next = urlencode($_SERVER['REQUEST_URI'] ?? '/');
            header("Location: /login?next={$next}");
            exit;
        }

        // Session timeout check
        $settings = Settings::getInstance();
        $lastActivity = $_SESSION['lastActivity'] ?? 0;
        if ($lastActivity > 0 && (time() - $lastActivity) > $settings->sessionTimeout) {
            $_SESSION = [];
            session_destroy();
            header('Location: /login?expired=1');
            exit;
        }
        $_SESSION['lastActivity'] = time();

        // Session IP change detection
        if ($settings->sessionValidateIp) {
            $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
            $loginIp = $_SESSION['loginIp'] ?? '';
            if ($loginIp !== '' && $currentIp !== $loginIp) {
                $_SESSION = [];
                session_destroy();
                header('Location: /login?ip_changed=1');
                exit;
            }
        }

        // IP restriction check
        if (!empty($settings->allowedIpRanges)) {
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
            if (!self::isIpAllowed($clientIp, $settings->allowedIpRanges)) {
                http_response_code(403);
                echo 'Access denied: IP not allowed';
                exit;
            }
        }

        CsrfProtection::validateToken();
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
        $rangeList = array_filter(array_map('trim', explode(',', $ranges)), fn(string $cidr) => $cidr !== '');
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
