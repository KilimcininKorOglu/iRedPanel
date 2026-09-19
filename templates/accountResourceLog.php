<?php $pageTitle = $t('resource.log_title', ['host' => $resource->host ?: "#{$resource->id}"]); ?>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <h1><?= $e($pageTitle) ?></h1>
  <div class="d-flex gap-2">
    <form method="post" action="/account-resources/<?= $e($resource->id) ?>/replicate">
      <?= $csrfField ?>
      <button type="submit" class="btn btn-primary"<?= $resource->host === '' ? ' disabled' : '' ?>><i class="bi bi-arrow-repeat me-1"></i><?= $te('resource.replicate_now') ?></button>
    </form>
    <a class="btn btn-outline-secondary" href="/account-resources/<?= $e($resource->id) ?>"><i class="bi bi-pencil me-1"></i><?= $te('common.edit') ?></a>
    <a class="btn btn-outline-secondary" href="/account-resources"><i class="bi bi-arrow-left me-1"></i><?= $te('common.back') ?></a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead>
            <tr>
              <th><?= $te('resource.started') ?></th>
              <th><?= $te('common.status') ?></th>
              <th><?= $te('resource.changes') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($runs as $run): ?>
            <tr<?= (int) $run['id'] === $selectedRun ? ' class="table-active"' : '' ?>>
              <td class="text-nowrap"><a href="?<?= $e(http_build_query(['run' => $run['id'], 'page' => $paginatedResult->currentPage])) ?>"><?= $e(date('Y-m-d H:i:s', (int) $run['started_at'])) ?></a></td>
              <td><span class="badge badge-status <?= $tone('replication_status', $run['status']) ?>"><?= $te("resource.status_{$run['status']}") ?></span></td>
              <td class="small">
                <?php foreach ($run['counts'] as $action => $count): ?>
                <span class="badge badge-status <?= $tone('replication_action', $action) ?>"><?= $te("resource.action_{$action}") ?> <?= $e($count) ?></span>
                <?php endforeach; ?>
                <?php if ($run['counts'] === []): ?><span class="text-body-secondary"><?= $te('resource.no_changes') ?></span><?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if ($runs === []): ?>
            <tr><td colspan="3" class="text-center text-body-secondary py-4"><?= $te('resource.log_empty') ?></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php include __DIR__ . '/pagination.php'; ?>
  </div>
  <div class="col-lg-7">
    <?php foreach ($runs as $run): if ((int) $run['id'] !== $selectedRun) { continue; } ?>
    <?php if ($run['message'] !== ''): ?>
    <div class="alert <?= $run['status'] === 'failed' ? 'alert-danger' : 'alert-warning' ?>">
      <?= str_starts_with($run['message'], 'held_back:') ? $te('resource.held_back', ['count' => (int) substr($run['message'], 10)]) : $e($run['message']) ?>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
    <div class="card">
      <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
          <thead>
            <tr>
              <th><?= $te('resource.event') ?></th>
              <th><?= $te('common.type') ?></th>
              <th><?= $te('common.email') ?></th>
              <th><?= $te('resource.detail') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($events as $event): ?>
            <tr>
              <td><span class="badge badge-status <?= $tone('replication_action', $event['action']) ?>"><?= $te("resource.action_{$event['action']}") ?></span></td>
              <td><?= $te("resource.kind_{$event['kind']}") ?></td>
              <td><?= $e($event['address']) ?></td>
              <td class="small text-body-secondary text-break"><?= $e(\App\Services\Replication\EventDetail::describe($event['action'], $event['detail'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if ($events === []): ?>
            <tr><td colspan="4" class="text-center text-body-secondary py-4"><?= $te('resource.no_events') ?></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
