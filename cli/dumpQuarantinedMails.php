<?php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    echo "This script can only be run from the command line.\n";
    exit(1);
}

require_once __DIR__ . '/bootstrap.php';

use App\Repositories\RepositoryFactory;

/**
 * @return ?int the start of the day in UTC, or null without --since
 */
function sinceTime(array $options): ?int
{
    if (!isset($options['since'])) {
        return null;
    }
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $options['since'], new \DateTimeZone('UTC'));
    if ($date === false || $date->format('Y-m-d') !== $options['since']) {
        fwrite(STDERR, "Error: --since must be a date in the form YYYY-MM-DD.\n");
        exit(1);
    }

    return $date->getTimestamp();
}

$options = getopt('', ['output:', 'since:']);

if (empty($options['output'])) {
    echo "Usage: php cli/dumpQuarantinedMails.php --output=/path/to/dir [--since=YYYY-MM-DD]\n";
    echo "\nExports quarantined emails from Amavisd database to .eml files.\n";
    exit(1);
}

$outputDir = rtrim($options['output'], '/');
if (!is_dir($outputDir) || !is_writable($outputDir)) {
    fwrite(STDERR, "Error: Output directory does not exist or is not writable: {$outputDir}\n");
    exit(1);
}
$since = sinceTime($options);

$repo = RepositoryFactory::getAmavisdRepository();
$count = 0;
foreach ($repo->getQuarantinedMailIds($since) as $mailId) {
    $mailText = $repo->getQuarantinedMailText($mailId);
    if ($mailText === '') {
        continue;
    }
    $filename = 'spam-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $mailId) . '.eml';
    if (file_put_contents("{$outputDir}/{$filename}", $mailText) === false) {
        fwrite(STDERR, "Error: Cannot write {$outputDir}/{$filename}\n");
        exit(1);
    }
    $count++;
}

echo "Exported {$count} quarantined message(s) to {$outputDir}\n";
