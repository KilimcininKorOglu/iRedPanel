<?php

declare(strict_types=1);

/**
 * Cleans up old Amavisd quarantine and mail log records.
 * Designed to run as a cron job.
 *
 * Usage: php cli/cleanupAmavisdDb.php [--quarantine-days=N] [--maillog-days=N]
 * The defaults are IREDPANEL_AMAVISD_REMOVE_QUARANTINED_IN_DAYS and
 * IREDPANEL_AMAVISD_REMOVE_MAILLOG_IN_DAYS, as for the cleanup button on the web page.
 */

require_once __DIR__ . '/bootstrap.php';

use App\Models\Settings;
use App\Repositories\RepositoryFactory;
use App\Utils\WholeNumber;

/**
 * A retention of 0 days, or a value that is not a whole number, would delete every
 * record, so the script stops instead.
 */
function retentionDays(array $options, string $name, int $default): int
{
    if (!array_key_exists($name, $options)) {
        return $default;
    }
    $days = is_string($options[$name]) ? WholeNumber::parse($options[$name]) : null;
    if ($days === null || $days < 1) {
        fwrite(STDERR, "Error: --{$name} must be a whole number of 1 or more.\n");
        exit(1);
    }

    return $days;
}

$settings = Settings::getInstance();

if (!$settings->amavisdEnabled) {
    echo "Amavisd integration is not enabled.\n";
    exit(0);
}

$options = getopt('', ['quarantine-days:', 'maillog-days:']);
$quarantineDays = retentionDays($options, 'quarantine-days', $settings->amavisdRemoveQuarantinedInDays);
$maillogDays = retentionDays($options, 'maillog-days', $settings->amavisdRemoveMaillogInDays);

$repo = RepositoryFactory::getAmavisdRepository();

echo "Cleaning quarantined messages older than {$quarantineDays} days...\n";
$quarantineDeleted = $repo->cleanupQuarantined($quarantineDays);
echo "Deleted: {$quarantineDeleted} records\n";

echo "Cleaning mail log records older than {$maillogDays} days...\n";
$logDeleted = $repo->cleanupMailLog($maillogDays);
echo "Deleted: {$logDeleted} records\n";

echo "Done.\n";
