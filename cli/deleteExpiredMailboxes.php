<?php

declare(strict_types=1);

/**
 * Deletes mailbox directories for expired deleted_mailboxes records.
 * Designed to run as a cron job.
 *
 * Usage: php cli/deleteExpiredMailboxes.php [--dry-run]
 */

require_once __DIR__ . '/bootstrap.php';

use App\Repositories\DeletedMailboxRepositoryInterface;
use App\Repositories\RepositoryFactory;

/**
 * Returns why the maildir cannot be judged here, or null. The storage node is the
 * first directory under the vmail base (for example vmail1); when it is missing, the
 * mail storage is not mounted on this host and a missing maildir says nothing.
 */
function storageError(string $maildir, string $vmailBase): ?string
{
    if (!str_starts_with($maildir, $vmailBase . DIRECTORY_SEPARATOR)) {
        return 'Path outside vmail base directory, skipping.';
    }
    $node = explode(DIRECTORY_SEPARATOR, substr($maildir, strlen($vmailBase) + 1))[0];
    if (!is_dir($vmailBase . DIRECTORY_SEPARATOR . $node)) {
        return "Mail storage {$vmailBase}/{$node} not found on this host, keeping the record.";
    }

    return null;
}

function deleteTree(string $maildir): void
{
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
}

/**
 * @return bool false when the record stays because of an error
 */
function processDeletion(DeletedMailboxRepositoryInterface $repo, int $id, string $maildir, string $vmailBase, bool $dryRun): bool
{
    $storageError = storageError($maildir, $vmailBase);
    if ($storageError !== null) {
        echo "  {$storageError}\n";
        return false;
    }

    if (!is_dir($maildir)) {
        echo $dryRun ? "  Directory not found, would remove the record.\n" : "  Directory not found, removing the record.\n";
    } elseif ($dryRun) {
        echo "  Would delete: {$maildir}\n";
    } else {
        deleteTree($maildir);
        echo "  Deleted directory.\n";
    }

    if (!$dryRun) {
        $repo->cancelDeletion($id);
    }
    return true;
}

$options = getopt('', ['dry-run']);
$dryRun = isset($options['dry-run']);

if ($dryRun) {
    echo "DRY RUN: no files or records will be deleted.\n\n";
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
    echo "Processing: {$deletion->username}: {$deletion->maildir}\n";
    try {
        $done = processDeletion($repo, $deletion->id, $deletion->maildir, $vmailBase, $dryRun);
    } catch (\Exception $e) {
        echo "  Error: {$e->getMessage()}\n";
        $done = false;
    }
    $done ? $processed++ : $errors++;
}

echo "\nDone. Processed: {$processed}, Errors: {$errors}\n";
