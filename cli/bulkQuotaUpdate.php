<?php

declare(strict_types=1);

/**
 * Bulk quota update from a CSV file.
 * CSV format: email,quotaMb (one per line, no header).
 *
 * Usage: php cli/bulkQuotaUpdate.php --file=quotas.csv
 */

require_once __DIR__ . '/bootstrap.php';

use App\Models\User;
use App\Repositories\RepositoryFactory;

/**
 * Checks one line as the user edit page and the REST API do: the mailbox exists,
 * the quota is a whole number of 0 or more (0 = unlimited), and it fits the domain limits.
 *
 * @return array{User, string}|string the mailbox with its new quota and its domain, or why the line is skipped
 */
function prepareQuota(string $email, string $quotaField): array|string
{
    [$uid, $domain] = array_pad(explode('@', strtolower($email), 2), 2, '');
    if ($uid === '' || $domain === '') {
        return "invalid email '{$email}'";
    }
    $user = RepositoryFactory::getUserRepository()->getUser($domain, $uid);
    if ($user === null) {
        return "not found: {$email}";
    }
    if ($quotaField === '') {
        return "no quota for: {$email}";
    }

    try {
        $quota = User::validMailQuota($quotaField);
    } catch (\InvalidArgumentException $e) {
        return "{$email}: {$e->getMessage()}";
    }
    $limitError = RepositoryFactory::getDomainRepository()->getDomain($domain)?->quotaChangeError($user->mailQuota, $quota);
    if ($limitError !== null) {
        return "{$email}: {$limitError->getMessage()}";
    }
    $user->mailQuota = $quota;

    return [$user, $domain];
}

$options = getopt('', ['file:']);

if (empty($options['file'])) {
    echo "Usage: php cli/bulkQuotaUpdate.php --file=quotas.csv\n";
    exit(1);
}

$file = $options['file'];
if (!file_exists($file)) {
    echo "File not found: {$file}\n";
    exit(1);
}

$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$userRepo = RepositoryFactory::getUserRepository();
$success = 0;
$failed = 0;

foreach ($lines as $lineNum => $line) {
    $parts = array_map('trim', str_getcsv($line, escape: ''));
    if (count($parts) < 2) {
        echo "Skipping line " . ($lineNum + 1) . ": invalid format\n";
        $failed++;
        continue;
    }

    $prepared = prepareQuota($parts[0], $parts[1]);
    if (is_string($prepared)) {
        echo "Skipping line " . ($lineNum + 1) . ": {$prepared}\n";
        $failed++;
        continue;
    }

    [$user, $domain] = $prepared;
    $email = "{$user->uid}@{$domain}";
    try {
        $userRepo->updateUser($domain, $user);
        echo "Updated: {$email} → {$user->mailQuota} MB\n";
        $success++;
    } catch (\Exception $e) {
        echo "Failed: {$email}: {$e->getMessage()}\n";
        $failed++;
    }
}

echo "\nDone. Success: {$success}, Failed: {$failed}\n";
