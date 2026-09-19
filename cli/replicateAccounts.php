<?php

declare(strict_types=1);

/**
 * Replicates mail accounts from the account resources (Active Directory, Samba AD).
 * Run it from cron every minute; each resource runs when its interval has passed.
 *
 * Usage: php cli/replicateAccounts.php [--resource=ID] [--force] [--dry-run] [--allow-mass-disable]
 *   --resource=ID          run only this resource
 *   --force                run even when the interval has not passed or the resource is disabled
 *   --dry-run              print the planned actions and change nothing
 *   --allow-mass-disable   disable accounts even when most of them disappeared from the directory
 */

require_once __DIR__ . '/bootstrap.php';

use App\Models\AccountResource;
use App\Repositories\RepositoryFactory;
use App\Services\Replication\ReplicationRunner;
use App\Utils\WholeNumber;

/**
 * @return AccountResource[] the resources to run
 */
function selectResources(array $options, bool $force): array
{
    $repo = RepositoryFactory::getAccountResourceRepository();
    if (!array_key_exists('resource', $options)) {
        return array_filter($repo->all(), static fn(AccountResource $r): bool => $force || $r->isDue(time()));
    }
    $id = is_string($options['resource']) ? WholeNumber::parse($options['resource']) : null;
    $resource = $id === null || $id === 0 ? null : $repo->find($id);
    if ($resource === null) {
        fwrite(STDERR, "Error: --resource must be the id of an account resource.\n");
        exit(1);
    }
    if (!$force && !$resource->isDue(time())) {
        echo "Resource #{$resource->id} is disabled or its interval has not passed. Use --force to run it now.\n";
        return [];
    }

    return [$resource];
}

function printPlan(ReplicationRunner $runner, AccountResource $resource, bool $allowMassDisable): void
{
    $plan = $runner->plan($resource, $allowMassDisable);
    foreach ($plan->actions as $action) {
        echo "  {$action->type} {$action->kind} {$action->address()}" . ($action->detail !== '' ? " ({$action->detail})" : '') . "\n";
    }
    echo '  ' . count($plan->actions) . " actions planned, {$plan->heldBack} disables held back.\n";
}

/**
 * @return bool false when the run failed
 */
function runResource(ReplicationRunner $runner, AccountResource $resource, bool $allowMassDisable): bool
{
    $result = $runner->run($resource, $allowMassDisable);
    if ($result === null) {
        echo "  Skipped: another replication of this resource is running.\n";
        return true;
    }
    $counts = implode(', ', array_map(static fn(string $k, int $v): string => "{$k}={$v}", array_keys($result->counts), $result->counts));
    echo "  Status: {$result->status()}" . ($counts !== '' ? ", {$counts}" : '') . "\n";
    if ($result->message() !== '') {
        echo "  Message: {$result->message()}\n";
    }

    return $result->status() !== AccountResource::STATUS_FAILED;
}

$repo = RepositoryFactory::getAccountResourceRepository();
if (!$repo->isAvailable()) {
    fwrite(STDERR, "Error: account replication needs the iredadmin database (IREDPANEL_IREDADMIN_DB_*).\n");
    exit(1);
}
$repo->ensureTablesExist();

$options = getopt('', ['resource:', 'force', 'dry-run', 'allow-mass-disable']);
$dryRun = array_key_exists('dry-run', $options);
$allowMassDisable = array_key_exists('allow-mass-disable', $options);
$runner = ReplicationRunner::create();

$ok = true;
foreach (selectResources($options, array_key_exists('force', $options)) as $resource) {
    echo "Resource #{$resource->id} ({$resource->host}:{$resource->port} -> {$resource->domain}):\n";
    try {
        if ($dryRun) {
            printPlan($runner, $resource, $allowMassDisable);
        } else {
            $ok = runResource($runner, $resource, $allowMassDisable) && $ok;
        }
    } catch (\Exception $e) {
        fwrite(STDERR, "  Error: {$e->getMessage()}\n");
        $ok = false;
    }
}

exit($ok ? 0 : 1);
