<?php
$pageTitle = $t('compat.title');
$backendNames = ['ldap' => 'LDAP', 'mysql' => 'MySQL/MariaDB', 'pgsql' => 'PostgreSQL'];
?>
<div class="page-header">
  <div>
    <h1><?= $te('compat.title') ?></h1>
    <div class="small text-body-secondary mt-1"><?= $te('compat.intro') ?></div>
  </div>
</div>

<?php if ($report['error']): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= $te('compat.unavailable') ?></div>
<?php else: ?>
<?php if ($report['current'] === null): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>v<?= $e($installedVersion) ?>: <?= $te('compat.not_listed') ?></div>
<?php endif; ?>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <th>iRedPanel</th>
          <th><?= $te('compat.compatible') ?></th>
          <th><?= $te('compat.backends') ?></th>
          <th><?= $te('compat.released') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($report['releases'] as $release): ?>
        <?php $installed = $release['version'] === $installedVersion; ?>
        <tr<?= $installed ? ' class="table-active"' : '' ?>>
          <td class="fw-medium text-nowrap">
            v<?= $e($release['version']) ?>
            <?php if ($installed): ?><span class="badge tone-badge tone-cyan ms-2"><?= $te('compat.installed_badge') ?></span><?php endif; ?>
          </td>
          <td>
            <?php foreach ($release['iredmail'] as $version): ?>
            <span class="badge tone-badge tone-<?= $installed ? 'green' : 'blue' ?>"><?= $e($version) ?></span>
            <?php endforeach; ?>
          </td>
          <td><?= $e(implode(', ', array_map(static fn (string $b): string => $backendNames[$b], $release['backends']))) ?></td>
          <td class="text-body-secondary text-nowrap"><?= $e($release['date']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer small text-body-secondary">
    <p class="mb-1"><i class="bi bi-info-circle me-1"></i><?= $te('compat.upgrade_hint') ?></p>
    <p class="mb-0">
      <?php if ($report['source'] === 'remote'): ?>
      <?= $te('compat.source_remote', ['time' => date('Y-m-d H:i', (int) $report['checkedAt'])]) ?>
      <?php elseif ($report['source'] === 'offline'): ?>
      <span class="text-warning"><?= $te('compat.source_offline') ?></span>
      <?php else: ?>
      <?= $te('compat.source_local') ?>
      <?php endif; ?>
    </p>
  </div>
</div>
<?php endif; ?>
