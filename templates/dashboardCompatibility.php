<?php
// Included by dashboard.php for global admins. $compatibility comes from DashboardController.
$backendNames = ['ldap' => 'LDAP', 'mysql' => 'MySQL/MariaDB', 'pgsql' => 'PostgreSQL'];
$versionBadges = static function (array $versions, string $tone) use ($e): string {
    return implode(' ', array_map(
        static fn (string $v): string => '<span class="badge tone-badge tone-' . $tone . '">' . $e($v) . '</span>',
        $versions
    ));
};
$current = $compatibility['current'] ?? null;
?>
<h2 class="h5 mt-4 mb-3"><?= $te('dashboard.compat_title') ?></h2>
<div class="card">
  <?php if ($compatibility['error']): ?>
  <div class="card-body text-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= $te('dashboard.compat_unavailable') ?></div>
  <?php else: ?>
  <div class="card-body">
    <dl class="row mb-0">
      <dt class="col-md-4 text-body-secondary fw-normal"><?= $te('dashboard.compat_installed') ?></dt>
      <dd class="col-md-8">v<?= $e($systemInfo['iredpanelVersion']) ?></dd>
      <dt class="col-md-4 text-body-secondary fw-normal"><?= $te('dashboard.compat_tested') ?></dt>
      <dd class="col-md-8">
        <?php if ($current !== null): ?>
        <?= $versionBadges($current['iredmail'], 'green') ?>
        <?php else: ?>
        <span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= $te('dashboard.compat_not_listed') ?></span>
        <?php endif; ?>
      </dd>
      <?php if ($current !== null): ?>
      <dt class="col-md-4 text-body-secondary fw-normal"><?= $te('dashboard.compat_backends') ?></dt>
      <dd class="col-md-8 mb-0"><?= $e(implode(', ', array_map(static fn (string $b): string => $backendNames[$b], $current['backends']))) ?></dd>
      <?php endif; ?>
    </dl>
  </div>

  <div class="card-header border-top"><?= $te('dashboard.compat_newer') ?></div>
  <?php if ($compatibility['newer'] === []): ?>
  <div class="card-body text-body-secondary"><?= $te('dashboard.compat_up_to_date') ?></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <th>iRedPanel</th>
          <th><?= $te('dashboard.compat_tested') ?></th>
          <th><?= $te('dashboard.compat_released') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($compatibility['newer'] as $release): ?>
        <tr>
          <td class="fw-medium">v<?= $e($release['version']) ?></td>
          <td><?= $versionBadges($release['iredmail'], 'blue') ?></td>
          <td class="text-body-secondary"><?= $e($release['date']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <div class="card-footer small text-body-secondary">
    <p class="mb-1"><i class="bi bi-info-circle me-1"></i><?= $te('dashboard.compat_upgrade_hint') ?></p>
    <p class="mb-0">
      <?php if ($compatibility['source'] === 'remote'): ?>
      <?= $te('dashboard.compat_source_remote', ['time' => date('Y-m-d H:i', (int) $compatibility['checkedAt'])]) ?>
      <?php elseif ($compatibility['source'] === 'offline'): ?>
      <span class="text-warning"><?= $te('dashboard.compat_source_offline') ?></span>
      <?php else: ?>
      <?= $te('dashboard.compat_source_local') ?>
      <?php endif; ?>
    </p>
  </div>
  <?php endif; ?>
</div>
