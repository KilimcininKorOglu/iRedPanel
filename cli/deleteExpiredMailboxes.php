<?php

declare(strict_types=1);

/**
 * Deletes mailbox directories for expired deleted_mailboxes records.
 * Designed to run as a cron job.
 *
 * Usage: php cli/deleteExpiredMailboxes.php [--dry-run]
 */

require_once __DIR__ . '/bootstrap.php';

use App\Repositories\RepositoryFactory;

$options = getopt('', ['dry-run']);
$dryRun = isset($options['dry-run']);

if ($dryRun) {
    echo "DRY RUN — no files will be deleted.\n\n";
}

// Without the mail storage, every maildir looks missing and every record would be dropped.
$vmailPath = \App\Models\Settings::getInstance()->vmailPath;
$vmailBase = realpath($vmailPath);
if ($vmailBase === false || !is_dir($vmailBase)) {
    fwrite(STDERR, "Error: vmail base directory {$vmailPath} not found. Run this script on the mail server or set IREDPANEL_VMAIL_PATH.\n");
    exit(1);
}

$repo = RepositoryFactory::getDeletedMailboxRepository();

$processed = 0;
$errors = 0;

foreach ($repo->getExpiredDeletions() as $deletion) {
    $id = $deletion->id;
    $maildir = $deletion->maildir;
    $username = $deletion->username;

    echo "Processing: {$username} — {$maildir}\n";

    if (!is_dir($maildir)) {
        echo "  Directory not found, removing record.\n";
        if (!$dryRun) {
            $repo->cancelDeletion($id);
        }
        $processed++;
        continue;
    }

    // Safety: verify the directory is within the vmail base path
    $resolvedMaildir = realpath($maildir);
    if ($resolvedMaildir === false
        || !str_starts_with($resolvedMaildir, $vmailBase . DIRECTORY_SEPARATOR)) {
        echo "  Path outside vmail base directory, skipping.\n";
        $errors++;
        continue;
    }

    if ($dryRun) {
        echo "  Would delete: {$maildir}\n";
    } else {
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($maildir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }
            rmdir($maildir);
            echo "  Deleted directory.\n";

            $repo->cancelDeletion($id);
        } catch (\Exception $e) {
            echo "  Error: {$e->getMessage()}\n";
            $errors++;
            continue;
        }
    }

    $processed++;
}

echo "\nDone. Processed: {$processed}, Errors: {$errors}\n";
