<?php $pageTitle = $t('resource.title'); ?>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <h1><?= $te('resource.title') ?></h1>
  <div class="d-flex gap-2">
    <?php foreach ($types as $type): ?>
    <form method="post" action="/account-resources">
      <?= $csrfField ?>
      <input type="hidden" name="type" value="<?= $e($type) ?>">
      <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i><?= $te("resource.add_{$type}") ?></button>
    </form>
    <?php endforeach; ?>
  </div>
</div>

<p class="text-body-secondary"><?= $te('resource.intro') ?></p>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <th><?= $te('common.type') ?></th>
          <th><?= $te('resource.server') ?></th>
          <th><?= $te('common.domain') ?></th>
          <th><?= $te('resource.interval') ?></th>
          <th><?= $te('resource.last_run') ?></th>
          <th><?= $te('common.status') ?></th>
          <th class="text-end"><?= $te('common.actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($resources as $r): ?>
        <tr>
          <td class="fw-medium"><?= $te("resource.type_{$r->type}") ?></td>
          <td class="text-nowrap"><?= $r->host === '' ? '<span class="text-body-secondary">' . $te('resource.not_configured') . '</span>' : $e("{$r->host}:{$r->port}") ?></td>
          <td><?= $e($r->domain) ?></td>
          <td><?= $te('resource.every_minutes', ['minutes' => $r->intervalMinutes]) ?></td>
          <td class="text-nowrap">
            <?php if ($r->lastRunAt === null): ?>
            <span class="text-body-secondary"><?= $te('resource.never') ?></span>
            <?php else: ?>
            <?= $e(date('Y-m-d H:i:s', $r->lastRunAt)) ?>
            <span class="badge badge-status <?= $tone('replication_status', $r->lastStatus) ?>"><?= $te("resource.status_{$r->lastStatus}") ?></span>
            <?php endif; ?>
          </td>
          <td class="text-nowrap">
            <form method="post" action="/account-resources/<?= $e($r->id) ?>/toggle">
              <?= $csrfField ?>
              <button type="submit" class="btn btn-sm <?= $r->enabled ? 'btn-outline-success' : 'btn-outline-secondary' ?>" title="<?= $te($r->enabled ? 'resource.disable' : 'resource.enable') ?>">
                <i class="bi <?= $r->enabled ? 'bi-toggle-on' : 'bi-toggle-off' ?> me-1"></i><?= $te($r->enabled ? 'common.enabled' : 'common.disabled') ?>
              </button>
            </form>
          </td>
          <td class="text-nowrap">
            <div class="table-actions flex-nowrap">
              <a class="btn btn-sm btn-outline-secondary" href="/account-resources/<?= $e($r->id) ?>"><i class="bi bi-pencil me-1"></i><?= $te('common.edit') ?></a>
              <form method="post" action="/account-resources/<?= $e($r->id) ?>/replicate">
                <?= $csrfField ?>
                <button type="submit" class="btn btn-sm btn-outline-secondary" title="<?= $te('resource.replicate_now') ?>" aria-label="<?= $te('resource.replicate_now') ?>"<?= $r->host === '' ? ' disabled' : '' ?>><i class="bi bi-arrow-repeat"></i></button>
              </form>
              <a class="btn btn-sm btn-outline-secondary" href="/account-resources/<?= $e($r->id) ?>/log" title="<?= $te('resource.log') ?>" aria-label="<?= $te('resource.log') ?>"><i class="bi bi-journal-text"></i></a>
              <form method="post" action="/account-resources/<?= $e($r->id) ?>/delete" data-confirm="<?= $te('resource.delete_confirm') ?>">
                <?= $csrfField ?>
                <button type="submit" class="btn btn-sm btn-outline-danger" title="<?= $te('common.delete') ?>" aria-label="<?= $te('common.delete') ?>"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($resources)): ?>
        <tr><td colspan="7" class="text-center text-body-secondary py-4"><?= $te('resource.empty') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
