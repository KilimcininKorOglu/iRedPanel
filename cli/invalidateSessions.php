<?php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    echo "This script can only be run from the command line.\n";
    exit(1);
}

$options = getopt('', ['dry-run']);
$dryRun = isset($options['dry-run']);

$savePath = session_save_path();
if (empty($savePath)) {
    $savePath = sys_get_temp_dir();
}

if (!is_dir($savePath)) {
    echo "Error: Session save path does not exist: {$savePath}\n";
    exit(1);
}

$pattern = $savePath . '/sess_*';
$files = glob($pattern);

if ($files === false || $files === []) {
    echo "No session files found in {$savePath}\n";
    exit(0);
}

$count = count($files);

if ($dryRun) {
    echo "[DRY RUN] Would delete {$count} session file(s) from {$savePath}\n";
    foreach ($files as $file) {
        echo "  {$file}\n";
    }
    exit(0);
}

$deleted = 0;
foreach ($files as $file) {
    if (is_file($file) && @unlink($file)) {
        $deleted++;
    }
}

echo "Deleted {$deleted}/{$count} session file(s) from {$savePath}\n";
echo "All admins will need to re-login.\n";
