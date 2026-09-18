<?php

declare(strict_types=1);

/**
 * Exports users to CSV format.
 *
 * Usage: php cli/exportUsers.php --domain=example.com [--output=users.csv]
 */

require_once __DIR__ . '/bootstrap.php';

use App\Repositories\RepositoryFactory;

$options = getopt('', ['domain:', 'output::']);

if (empty($options['domain'])) {
    echo "Usage: php cli/exportUsers.php --domain=example.com [--output=users.csv]\n";
    exit(1);
}

$domain = $options['domain'];
$outputFile = $options['output'] ?? null;

$userRepo = RepositoryFactory::getUserRepository();
$users = $userRepo->getUsers($domain);

$header = ['email', 'name', 'first_name', 'last_name', 'quota_mb', 'active', 'global_admin'];

$output = fopen($outputFile ?? 'php://stdout', 'w');
fputcsv($output, $header, escape: '');

foreach ($users as $user) {
    fputcsv($output, [
        "{$user->uid}@{$domain}",
        $user->cn,
        $user->givenName,
        $user->sn,
        $user->mailQuota,
        $user->accountStatus ? '1' : '0',
        $user->domainGlobalAdmin ? '1' : '0',
    ], escape: '');
}

if ($outputFile) {
    fclose($output);
    echo "Exported " . count($users) . " users to {$outputFile}\n";
} else {
    fclose($output);
}
