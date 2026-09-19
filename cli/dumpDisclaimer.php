<?php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    echo "This script can only be run from the command line.\n";
    exit(1);
}

require_once __DIR__ . '/bootstrap.php';

use App\Repositories\RepositoryFactory;

$options = getopt('', ['output:']);

if (empty($options['output'])) {
    echo "Usage: php cli/dumpDisclaimer.php --output=/etc/postfix/disclaimer/\n";
    echo "\nExports domain disclaimer text to files for Postfix integration.\n";
    echo "Creates {domain}.txt and {domain}.html for each domain and alias domain with a disclaimer.\n";
    exit(1);
}

$outputDir = rtrim($options['output'], '/');
if (!is_dir($outputDir)) {
    echo "Error: Output directory does not exist: {$outputDir}\n";
    exit(1);
}

/**
 * Writes the disclaimer files of one mail domain, or removes them when the text is empty.
 *
 * @return bool true when files were written
 */
function writeDisclaimerFiles(string $outputDir, string $mailDomain, string $disclaimer): bool
{
    $txtFile = "{$outputDir}/{$mailDomain}.txt";
    $htmlFile = "{$outputDir}/{$mailDomain}.html";

    if ($disclaimer === '') {
        foreach ([$txtFile, $htmlFile] as $file) {
            if (file_exists($file)) {
                unlink($file);
                echo "  - Removed {$file}\n";
            }
        }
        return false;
    }

    $html = '<div id="disclaimer_separator"><p>----------</p></div>'
        . '<div id="disclaimer_text"><p>' . nl2br(htmlspecialchars($disclaimer, ENT_QUOTES, 'UTF-8')) . '</p></div>';
    if (file_put_contents($txtFile, "\n---------\n" . $disclaimer . "\n") === false
        || file_put_contents($htmlFile, "\n" . $html . "\n") === false) {
        throw new RuntimeException("Cannot write the disclaimer files of {$mailDomain} in {$outputDir}");
    }
    echo "  + {$mailDomain}\n";

    return true;
}

$domainRepo = RepositoryFactory::getDomainRepository();
$aliasRepo = RepositoryFactory::getDomainAliasRepository();
$count = 0;

foreach ($domainRepo->getDomains() as $domainInfo) {
    $domain = $domainRepo->getDomain($domainInfo['domainName']);
    if ($domain === null) {
        continue;
    }

    // Postfix sees the alias domain in the sender address, so it needs its own copy, as in dump_disclaimer.py.
    $mailDomains = [$domain->domainName];
    foreach ($aliasRepo->getAliasesForDomain($domain->domainName) as $alias) {
        $mailDomains[] = $alias->aliasDomain;
    }

    foreach ($mailDomains as $mailDomain) {
        $count += writeDisclaimerFiles($outputDir, $mailDomain, $domain->disclaimer) ? 1 : 0;
    }
}

echo "Done. {$count} disclaimer(s) exported to {$outputDir}\n";
