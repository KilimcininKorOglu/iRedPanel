<?php $pageTitle = $t('log.title'); ?>
<div class="container">
  <h1><?= $te('log.title') ?></h1>

  <?php if (!($loggingEnabled ?? false)): ?>
  <p class="text-error"><?= $te('log.not_configured') ?></p>
  <?php else: ?>

  <form method="get" style="margin-bottom: 1rem;">
    <div class="row">
      <div class="col-4">
        <label for="domain"><?= $te('common.domain') ?></label>
        <input id="domain" type="text" name="domain" placeholder="example.com" value="<?= $e($filterDomain ?? '') ?>" />
      </div>
      <div class="col-4">
        <label for="event"><?= $te('log.event') ?></label>
        <select id="event" name="event">
          <option value=""><?= $te('log.all_events') ?></option>
          <?php foreach (['login', 'create', 'update', 'delete', 'active', 'disable'] as $evt): ?>
          <option value="<?= $e($evt) ?>" <?php if (($filterEvent ?? '') === $evt): ?>selected<?php endif; ?>><?= $e($evt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-4" style="display:flex; align-items:flex-end;">
        <button type="submit" class="button outline"><?= $te('log.filter') ?></button>
        <a href="/logs" class="button outline" style="margin-left:0.5rem;"><?= $te('log.clear') ?></a>
      </div>
    </div>
  </form>

  <form method="post" action="/logs/delete">
    <?= $csrfField ?>
  <table class="striped">
    <thead>
      <tr>
        <?php if (!empty($session['isGlobalAdmin'])): ?>
        <th><input type="checkbox" id="selectAllLogs" onclick="document.querySelectorAll('input[name=\\'ids[]\\']').forEach(c=>c.checked=this.checked)" /></th>
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
        <td><input type="checkbox" name="ids[]" value="<?= $e($log['id'] ?? '') ?>" /></td>
        <?php endif; ?>
        <td><?= $e($log['timestamp'] ?? '') ?></td>
        <td><?= $e($log['admin'] ?? '') ?></td>
        <td><?= $e($log['ip'] ?? '') ?></td>
        <td><?= $e($log['event'] ?? '') ?></td>
        <td><?= $e($log['domain'] ?? '') ?></td>
        <td><?= $e($log['username'] ?? '') ?></td>
        <td><?= $e($log['msg'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($logs)): ?>
      <tr><td colspan="<?= !empty($session['isGlobalAdmin']) ? 8 : 7 ?>" class="text-light"><?= $te('log.empty') ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <?php if (!empty($session['isGlobalAdmin']) && !empty($logs)): ?>
  <div style="margin-top: 0.5rem;">
    <button type="submit" class="button error outline" data-confirm="<?= $te('log.delete_selected_confirm') ?>"><?= $te('log.delete_selected') ?></button>
    <button type="submit" name="deleteAll" value="1" class="button error" data-confirm="<?= $te('log.delete_all_confirm') ?>"><?= $te('log.delete_all') ?></button>
  </div>
  <?php endif; ?>
  </form>

  <?php if (isset($paginatedResult)): ?>
    <?php include __DIR__ . '/pagination.php'; ?>
  <?php endif; ?>

  <?php endif; ?>
</div>
