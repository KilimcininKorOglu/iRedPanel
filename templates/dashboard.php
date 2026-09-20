<?php $pageTitle = $t('dashboard.title'); ?>
<div class="page-header">
  <h1><?= $te('dashboard.title') ?></h1>
</div>

<?php $latest = $compatibility['newer'][0] ?? null; ?>
<?php if ($latest !== null): ?>
<div class="alert alert-info d-flex align-items-center gap-2">
  <i class="bi bi-arrow-up-circle"></i>
  <span><?= $te('dashboard.update_available', ['version' => $latest['version'], 'iredmail' => implode(', ', $latest['iredmail'])]) ?></span>
  <a href="https://github.com/KilimcininKorOglu/iRedPanel/releases/tag/v<?= $e($latest['version']) ?>" target="_blank" rel="noopener" class="ms-auto"><?= $te('dashboard.view_release') ?></a>
</div>
<?php endif; ?>

<?php if (empty($stats)): ?>
<a href="/domains" class="btn btn-primary"><?= $te('dashboard.manage_domains') ?></a>
<?php else: ?>
<div class="row g-3 mb-4">
  <div class="col-md-6 col-xl-4">
    <div class="card stat-card h-100 mb-0">
      <div class="card-body">
        <span class="stat-icon"><i class="bi bi-globe2"></i></span>
        <div class="flex-grow-1">
          <div class="stat-label"><?= $te('dashboard.domains') ?></div>
          <div class="stat-value"><?= $e($stats['totalDomains']) ?></div>
          <div class="small text-body-secondary mt-1">
            <?= $te('common.active') ?>: <?= $e($stats['activeDomains']) ?> &middot;
            <?= $te('common.disabled') ?>: <?= $e($stats['totalDomains'] - $stats['activeDomains']) ?>
          </div>
          <a href="/domains" class="small d-inline-block mt-2"><?= $te('dashboard.manage_domains') ?> <i class="bi bi-arrow-right"></i></a>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-4">
    <div class="card stat-card h-100 mb-0">
      <div class="card-body">
        <span class="stat-icon"><i class="bi bi-people"></i></span>
        <div class="flex-grow-1">
          <div class="stat-label"><?= $te('dashboard.users') ?></div>
          <div class="stat-value"><?= $e($stats['totalUsers']) ?></div>
          <div class="small text-body-secondary mt-1">
            <?= $te('common.active') ?>: <?= $e($stats['activeUsers']) ?> &middot;
            <?= $te('common.disabled') ?>: <?= $e($stats['totalUsers'] - $stats['activeUsers']) ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-4">
    <div class="card stat-card h-100 mb-0">
      <div class="card-body">
        <span class="stat-icon"><i class="bi bi-person-badge"></i></span>
        <div class="flex-grow-1">
          <div class="stat-label"><?= $te('dashboard.admins') ?></div>
          <div class="stat-value"><?= $e($stats['totalAdmins']) ?></div>
          <a href="/admins" class="small d-inline-block mt-2"><?= $te('dashboard.manage_admins') ?> <i class="bi bi-arrow-right"></i></a>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-4">
    <div class="card stat-card h-100 mb-0">
      <div class="card-body">
        <span class="stat-icon"><i class="bi bi-calendar-x"></i></span>
        <div class="flex-grow-1">
          <div class="stat-label"><?= $te('dashboard.expiring_accounts') ?></div>
          <div class="stat-value"><?= $e($stats['expiredUsers'] ?? 0) ?></div>
          <div class="small text-body-secondary mt-1">
            <?= $te('dashboard.expired_mailboxes') ?> &middot;
            <?= $te('dashboard.expiring_mailboxes', ['days' => \App\Utils\ExpiryDate::SOON_DAYS]) ?>: <?= $e($stats['expiringUsers'] ?? 0) ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card stat-card h-100 mb-0">
      <div class="card-body">
        <span class="stat-icon"><i class="bi bi-hdd"></i></span>
        <div class="flex-grow-1">
          <div class="stat-label"><?= $te('common.quota') ?></div>
          <div class="stat-value"><?= $e(number_format($stats['totalQuotaAllocated'])) ?> <small class="fs-6 text-body-secondary">MB</small></div>
          <div class="small text-body-secondary mt-1">
            <?= $te('dashboard.allocated') ?> &middot;
            <?= $te('dashboard.used') ?>: <?= $e(number_format($stats['totalQuotaUsed'])) ?> MB
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card stat-card h-100 mb-0">
      <div class="card-body">
        <span class="stat-icon"><i class="bi bi-envelope"></i></span>
        <div class="flex-grow-1">
          <div class="stat-label"><?= $te('dashboard.messages') ?></div>
          <div class="stat-value"><?= $e(number_format($stats['totalMessages'])) ?></div>
          <div class="small text-body-secondary mt-1"><?= $te('dashboard.total_stored') ?></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($systemInfo)): ?>
<h2 class="h5 mb-3"><?= $te('dashboard.system_info') ?></h2>
<div class="row g-3">
  <div class="col-md-6">
    <div class="card h-100 mb-0">
      <div class="card-header"><i class="bi bi-server me-2"></i><?= $te('dashboard.server') ?></div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-5 text-body-secondary fw-normal"><?= $te('dashboard.hostname') ?></dt>
          <dd class="col-7"><?= $e($systemInfo['hostname']) ?></dd>
          <?php if ($systemInfo['uptime'] !== null): ?>
          <dt class="col-5 text-body-secondary fw-normal"><?= $te('dashboard.uptime') ?></dt>
          <dd class="col-7"><?= $e($systemInfo['uptime']['days']) ?>d <?= $e($systemInfo['uptime']['hours']) ?>h <?= $e($systemInfo['uptime']['minutes']) ?>m</dd>
          <?php endif; ?>
          <dt class="col-5 text-body-secondary fw-normal"><?= $te('dashboard.load') ?></dt>
          <dd class="col-7 mb-0"><?= $e(implode(', ', array_map(fn($v) => number_format((float) $v, 2), $systemInfo['loadAverage']))) ?></dd>
        </dl>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card h-100 mb-0">
      <div class="card-header"><i class="bi bi-box-seam me-2"></i><?= $te('dashboard.software') ?></div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-5 text-body-secondary fw-normal">PHP</dt>
          <dd class="col-7"><?= $e($systemInfo['phpVersion']) ?></dd>
          <dt class="col-5 text-body-secondary fw-normal">iRedPanel</dt>
          <dd class="col-7 mb-0">v<?= $e($systemInfo['iredpanelVersion']) ?></dd>
        </dl>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($compatibility)): ?>
<?php include __DIR__ . '/dashboardCompatibility.php'; ?>
<?php endif; ?>
