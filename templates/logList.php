<?php $pageTitle = 'Activity Log'; ?>
<div class="container">
  <h1>Activity Log</h1>

  <?php if (!($loggingEnabled ?? false)): ?>
  <p class="text-error">Activity logging is not configured. Set IREDPANEL_IREDADMIN_DB_* environment variables to enable.</p>
  <?php else: ?>

  <form method="get" style="margin-bottom: 1rem;">
    <div class="row">
      <div class="col-4">
        <label for="domain">Domain</label>
        <input id="domain" type="text" name="domain" placeholder="example.com" value="<?= $e($filterDomain ?? '') ?>" />
      </div>
      <div class="col-4">
        <label for="event">Event</label>
        <select id="event" name="event">
          <option value="">All events</option>
          <?php foreach (['login', 'create', 'update', 'delete', 'active', 'disable'] as $evt): ?>
          <option value="<?= $e($evt) ?>" <?php if (($filterEvent ?? '') === $evt): ?>selected<?php endif; ?>><?= $e($evt) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-4" style="display:flex; align-items:flex-end;">
        <button type="submit" class="button outline">Filter</button>
        <a href="/logs" class="button outline" style="margin-left:0.5rem;">Clear</a>
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
        <th>Timestamp</th>
        <th>Admin</th>
        <th>IP</th>
        <th>Event</th>
        <th>Domain</th>
        <th>User</th>
        <th>Message</th>
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
      <tr><td colspan="<?= !empty($session['isGlobalAdmin']) ? 8 : 7 ?>" class="text-light">No log entries found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <?php if (!empty($session['isGlobalAdmin']) && !empty($logs)): ?>
  <div style="margin-top: 0.5rem;">
    <button type="submit" class="button error outline" data-confirm="Delete selected log entries?">Delete selected</button>
    <button type="submit" name="deleteAll" value="1" class="button error" data-confirm="Delete ALL log entries? This cannot be undone.">Delete all</button>
  </div>
  <?php endif; ?>
  </form>

  <?php if (isset($paginatedResult)): ?>
    <?php include __DIR__ . '/pagination.php'; ?>
  <?php endif; ?>

  <?php endif; ?>
</div>
