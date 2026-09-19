<?php

declare(strict_types=1);

/**
 * Deletes the iRedAPD throttle rows that iRedAPD never applies. The throttle
 * plugin does not look up a CIDR network or a 'user@*' address, so such a row
 * limits nothing.
 *
 * Usage: php cli/deleteUnmatchedThrottles.php [--dry-run]
 */

require_once __DIR__ . '/bootstrap.php';

use App\Models\Settings;
use App\Repositories\RepositoryFactory;
use App\Utils\IredapdAccount;

if (!Settings::getInstance()->iredapdEnabled) {
    echo "iRedAPD integration is not enabled.\n";
    exit(0);
}

$dryRun = array_key_exists('dry-run', getopt('', ['dry-run']));
$repo = RepositoryFactory::getIredapdRepository();

$unmatched = array_values(array_filter(
    $repo->getThrottleAccounts(),
    static fn (string $account): bool => !IredapdAccount::isThrottleAccount($account),
));

if ($unmatched === []) {
    echo "No throttle rows to delete.\n";
    exit(0);
}

foreach ($unmatched as $account) {
    if ($dryRun) {
        echo "Would delete throttle rows of {$account}\n";
        continue;
    }
    $deleted = $repo->deleteThrottleSettings($account);
    echo "Deleted {$deleted} throttle rows of {$account}\n";
}

echo "Done.\n";
