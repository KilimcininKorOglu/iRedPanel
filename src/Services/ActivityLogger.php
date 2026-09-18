<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Settings;
use App\Repositories\Mysql\IredadminConnection;
use App\Repositories\Pgsql\IredadminPgsqlConnection;

/**
 * Logs admin activities to the iredadmin.log table.
 * Silently does nothing if logging is disabled or the iredadmin database is not configured.
 */
class ActivityLogger
{
    private static function getConnection(): ?object
    {
        return Settings::getInstance()->backend === 'pgsql'
            ? IredadminPgsqlConnection::getInstance()
            : IredadminConnection::getInstance();
    }

    /**
     * Event name that iRedAdmin writes for an "enable" action. The log table is
     * shared with iRedAdmin, so the panel writes the same name.
     */
    public const EVENT_ENABLE = 'active';

    public static function log(string $event, string $domain, string $username, string $msg, string $logLevel = 'info'): void
    {
        if (!Settings::getInstance()->activityLoggingEnabled) {
            return;
        }
        if ($event === 'enable') {
            $event = self::EVENT_ENABLE;
        }

        $conn = self::getConnection();
        if (!$conn->isAvailable()) {
            return;
        }

        $pdo = $conn->getPdo();
        $admin = $_SESSION['email'] ?? 'system';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO log (admin, ip, domain, username, event, loglevel, msg, timestamp)
                 VALUES (:admin, :ip, :domain, :username, :event, :loglevel, :msg, NOW())"
            );
            $stmt->execute([
                'admin' => $admin,
                'ip' => $ip,
                'domain' => $domain,
                'username' => $username,
                'event' => $event,
                'loglevel' => $logLevel,
                'msg' => $msg,
            ]);
        } catch (\Exception $e) {
            error_log("Activity logging failed: " . $e->getMessage());
        }
    }

    public static function logCreate(string $domain, string $username, string $msg): void
    {
        self::log('create', $domain, $username, $msg);
    }

    public static function logUpdate(string $domain, string $username, string $msg): void
    {
        self::log('update', $domain, $username, $msg);
    }

    public static function logDelete(string $domain, string $username, string $msg): void
    {
        self::log('delete', $domain, $username, $msg);
    }

    public static function logLogin(string $username): void
    {
        $domain = str_contains($username, '@') ? explode('@', $username, 2)[1] : '';
        self::log('login', $domain, $username, "Admin login successful");
    }
}
