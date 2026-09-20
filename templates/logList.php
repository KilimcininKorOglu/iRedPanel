<?php
$pageTitle = $t('log.title');
// A failed action (loglevel=error) is red whatever its event is.
$eventBadge = static fn (array $log): string => $tone('log_event', ($log['loglevel'] ?? '') === 'error' ? 'error' : (string) ($log['event'] ?? ''));
?>
<div class="page-header">
  <h1><?= $te('log.title') ?></h1>
</div>

<?php if (!($loggingEnabled ?? false)): ?>
<div class="alert alert-danger"><?= $te('log.not_configured') ?></div>
<?php else: ?>

<form method="get" class="card">
  <div class="card-body row g-3 align-items-end">
    <div class="col-md-4">
      <label for="domain" class="form-label"><?= $te('common.domain') ?></label><?= $help('common.domain') ?>
      <input id="domain" type="text" name="domain" class="form-control" placeholder="example.com" value="<?= $e($filterDomain ?? '') ?>" />
    </div>
    <div class="col-md-4">
      <label for="event" class="form-label"><?= $te('log.event') ?></label><?= $help('log.event') ?>
      <select id="event" name="event" class="form-select">
        <option value=""><?= $te('log.all_events') ?></option>
        <?php foreach (['login', 'create', 'update', 'delete', 'active', 'disable'] as $evt): ?>
        <option value="<?= $e($evt) ?>"<?php if (($filterEvent ?? '') === $evt): ?> selected<?php endif; ?>><?= $e($evt) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4 d-flex gap-2">
      <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-funnel me-1"></i><?= $te('log.filter') ?></button>
      <a href="/logs" class="btn btn-outline-secondary"><?= $te('log.clear') ?></a>
    </div>
  </div>
</form>

<form method="post" action="/logs/delete">
  <?= $csrfField ?>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr>
            <?php if (!empty($session['isGlobalAdmin'])): ?>
            <th><input type="checkbox" class="form-check-input" id="selectAllLogs" data-select-all="ids[]" /></th>
            <?php endif; ?>
            <th><?= $te('log.timestamp') ?></th>
            <th><?= $te('log.admin') ?></th>
            <th><?= $te('log.ip') ?></th>
            <th><?= $te('log.event') ?></th>
            <th><?= $te('common.domain') ?></th>
            <th><?= $te('log.user') ?></th>
            <th><?= $te('log.message') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
          <tr>
            <?php if (!empty($session['isGlobalAdmin'])): ?>
            <td><input type="checkbox" class="form-check-input" name="ids[]" value="<?= $e($log['id'] ?? '') ?>" /></td>
            <?php endif; ?>
            <td class="text-nowrap text-body-secondary"><?= $e($log['timestamp'] ?? '') ?></td>
            <td><?= $e($log['admin'] ?? '') ?></td>
            <td><code><?= $e($log['ip'] ?? '') ?></code></td>
            <td><span class="badge badge-status <?= $eventBadge($log) ?>"<?php if (($log['loglevel'] ?? '') === 'error'): ?> title="<?= $e($log['loglevel']) ?>"<?php endif; ?>><?php if (($log['loglevel'] ?? '') === 'error'): ?><i class="bi bi-exclamation-triangle-fill me-1"></i><?php endif; ?><?= $e($log['event'] ?? '') ?></span></td>
            <td><?= $e($log['domain'] ?? '') ?></td>
            <td><?= $e($log['username'] ?? '') ?></td>
            <td><?= $e($log['msg'] ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($logs)): ?>
          <tr><td colspan="<?= !empty($session['isGlobalAdmin']) ? 8 : 7 ?>" class="text-center text-body-secondary py-4"><?= $te('log.empty') ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (!empty($session['isGlobalAdmin']) && !empty($logs)): ?>
  <div class="d-flex flex-wrap gap-2">
    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="<?= $te('log.delete_selected_confirm') ?>"><i class="bi bi-trash3 me-1"></i><?= $te('log.delete_selected') ?></button>
    <button type="submit" name="deleteAll" value="1" class="btn btn-sm btn-danger" data-confirm="<?= $te('log.delete_all_confirm') ?>"><?= $te('log.delete_all') ?></button>
  </div>
  <?php endif; ?>
</form>

<?php if (isset($paginatedResult)): ?>
  <?php include __DIR__ . '/pagination.php'; ?>
<?php endif; ?>

<?php endif; ?>
