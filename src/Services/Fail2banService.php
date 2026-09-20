<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Settings;

/**
 * Interacts with Fail2ban via the fail2ban-client CLI command.
 */
class Fail2banService
{
    /**
     * Returns list of configured jail names.
     *
     * @return string[]
     */
    public static function getJails(): array
    {
        $settings = Settings::getInstance();
        return array_filter(array_map(trim(...), explode(',', $settings->fail2banJails)));
    }

    /**
     * Returns banned IPs for a specific jail.
     *
     * @return string[]
     */
    public static function getBannedIps(string $jail): array
    {
        $safeJail = escapeshellarg($jail);
        $output = self::executeCommand(self::buildCommand("status {$safeJail}"));

        if (preg_match('/Banned IP list:\s*(.+)$/m', $output, $matches)) {
            return array_filter(array_map(trim(...), explode(' ', $matches[1])));
        }

        return [];
    }

    /**
     * Returns all banned IPs grouped by jail.
     *
     * @return array<string, string[]>
     */
    public static function getAllBannedIps(): array
    {
        $result = [];
        foreach (self::getJails() as $jail) {
            try {
                $result[$jail] = self::getBannedIps($jail);
            } catch (\Exception $e) {
                $result[$jail] = [];
                error_log("Fail2ban status failed for jail '{$jail}': " . $e->getMessage());
            }
        }
        return $result;
    }

    public static function banIp(string $jail, string $ip): void
    {
        $safeJail = escapeshellarg($jail);
        $safeIp = escapeshellarg($ip);
        self::executeCommand(self::buildCommand("set {$safeJail} banip {$safeIp}"));
    }

    public static function unbanIp(string $jail, string $ip): void
    {
        $safeJail = escapeshellarg($jail);
        $safeIp = escapeshellarg($ip);
        self::executeCommand(self::buildCommand("set {$safeJail} unbanip {$safeIp}"));
    }

    private static function buildCommand(string $args): string
    {
        $settings = Settings::getInstance();
        $socketFlag = '';
        if (!empty($settings->fail2banSocket)) {
            $socketFlag = ' -s ' . escapeshellarg($settings->fail2banSocket);
        }
        return "fail2ban-client{$socketFlag} {$args}";
    }

    private static function executeCommand(string $command): string
    {
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException("Fail2ban command failed (exit {$returnCode}): " . implode("\n", $output));
        }

        return implode("\n", $output);
    }
}
