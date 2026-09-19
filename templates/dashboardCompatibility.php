<?php
// Included by dashboard.php for global admins. $compatibility comes from CompatibilityService::report().
$current = $compatibility['current'] ?? null;
?>
<h2 class="h5 mt-4 mb-3"><?= $te('compat.title') ?></h2>
<div class="card">
  <?php if ($compatibility['error']): ?>
  <div class="card-body text-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= $te('compat.unavailable') ?></div>
  <?php else: ?>
  <div class="card-body">
    <dl class="row mb-0">
      <dt class="col-md-4 text-body-secondary fw-normal"><?= $te('compat.installed') ?></dt>
      <dd class="col-md-8">v<?= $e($systemInfo['iredpanelVersion']) ?></dd>
      <dt class="col-md-4 text-body-secondary fw-normal"><?= $te('compat.tested') ?></dt>
      <dd class="col-md-8 mb-0">
        <?php if ($current !== null): ?>
        <?php foreach ($current['iredmail'] as $version): ?>
        <span class="badge tone-badge tone-green"><?= $e($version) ?></span>
        <?php endforeach; ?>
        <?php else: ?>
        <span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= $te('compat.not_listed') ?></span>
        <?php endif; ?>
      </dd>
    </dl>
  </div>
  <?php endif; ?>
  <div class="card-footer small">
    <a href="/compatibility"><?= $te('dashboard.compat_view_all') ?> <i class="bi bi-arrow-right"></i></a>
  </div>
</div>
