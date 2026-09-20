<?php

declare(strict_types=1);

/**
 * Disables every mailbox, domain and admin whose expiry date has passed. No open
 * source iRedMail component reads the expiry date, so this script enforces it.
 * Designed to run as a cron job.
 *
 * The script only disables. An account that gets a later date stays disabled
 * until an admin enables it again.
 *
 * Usage: php cli/disableExpiredAccounts.php [--dry-run]
 */

require_once __DIR__ . '/bootstrap.php';

use App\Services\AccountExpiry;

$options = getopt('', ['dry-run']);
$dryRun = isset($options['dry-run']);

if ($dryRun) {
    echo "DRY RUN: no account will be disabled.\n\n";
}

try {
    $disabled = AccountExpiry::create()->disableExpired($dryRun);
} catch (\Exception $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}

foreach ($disabled as $kind => $accounts) {
    foreach ($accounts as $account) {
        echo $dryRun ? "Would disable {$kind}: {$account}\n" : "Disabled {$kind}: {$account}\n";
    }
}

$total = count($disabled['mailboxes']) + count($disabled['domains']) + count($disabled['admins']);
echo "\nDone. Mailboxes: " . count($disabled['mailboxes'])
    . ", domains: " . count($disabled['domains'])
    . ", admins: " . count($disabled['admins'])
    . ", total: {$total}\n";
