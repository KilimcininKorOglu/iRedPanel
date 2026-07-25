<?php $pageTitle = $t('dashboard.title'); ?>
<div class="container">
  <h1><?= $te('dashboard.title') ?></h1>

  <?php if (!empty($newVersion)): ?>
  <div class="card" style="border-left: 4px solid var(--color-primary, #1a73e8); margin-bottom: 1rem;">
    <p><?= $te('dashboard.new_version', ['version' => $newVersion]) ?>
    <a href="https://github.com/KilimcininKorOglu/iRedPanel/releases/latest" target="_blank"><?= $te('dashboard.view_release') ?></a></p>
  </div>
  <?php endif; ?>

  <div class="row">
    <div class="col-4">
      <div class="card">
        <header>
          <h4><?= $te('dashboard.domains') ?></h4>
        </header>
        <p>
          <?= $te('dashboard.total') ?>: <strong><?= $e($stats['totalDomains']) ?></strong><br />
          <?= $te('common.active') ?>: <?= $e($stats['activeDomains']) ?><br />
          <?= $te('common.disabled') ?>: <?= $e($stats['totalDomains'] - $stats['activeDomains']) ?>
        </p>
        <a href="/domains" class="button primary outline"><?= $te('dashboard.manage_domains') ?></a>
      </div>
    </div>

    <div class="col-4">
      <div class="card">
        <header>
          <h4><?= $te('dashboard.users') ?></h4>
        </header>
        <p>
          <?= $te('dashboard.total') ?>: <strong><?= $e($stats['totalUsers']) ?></strong><br />
          <?= $te('common.active') ?>: <?= $e($stats['activeUsers']) ?><br />
          <?= $te('common.disabled') ?>: <?= $e($stats['totalUsers'] - $stats['activeUsers']) ?>
        </p>
      </div>
    </div>

    <div class="col-4">
      <div class="card">
        <header>
          <h4><?= $te('dashboard.admins') ?></h4>
        </header>
        <p>
          <?= $te('dashboard.total') ?>: <strong><?= $e($stats['totalAdmins']) ?></strong>
        </p>
        <a href="/admins" class="button primary outline"><?= $te('dashboard.manage_admins') ?></a>
      </div>
    </div>
  </div>

  <div class="row" style="margin-top: 1rem;">
    <div class="col-6">
      <div class="card">
        <header>
          <h4><?= $te('common.quota') ?></h4>
        </header>
        <p>
          <?= $te('dashboard.allocated') ?>: <strong><?= $e(number_format($stats['totalQuotaAllocated'])) ?> MB</strong><br />
          <?= $te('dashboard.used') ?>: <?= $e(number_format($stats['totalQuotaUsed'])) ?> MB
        </p>
      </div>
    </div>

    <div class="col-6">
      <div class="card">
        <header>
          <h4><?= $te('dashboard.messages') ?></h4>
        </header>
        <p>
          <?= $te('dashboard.total_stored') ?>: <strong><?= $e(number_format($stats['totalMessages'])) ?></strong>
        </p>
      </div>
    </div>
  </div>

  <?php if (!empty($systemInfo)): ?>
  <div class="row" style="margin-top: 1rem;">
    <div class="col">
      <h2><?= $te('dashboard.system_info') ?></h2>
    </div>
  </div>
  <div class="row">
    <div class="col-6">
      <div class="card">
        <header><h4><?= $te('dashboard.server') ?></h4></header>
        <p>
          <?= $te('dashboard.hostname') ?>: <strong><?= $e($systemInfo['hostname']) ?></strong><br />
          <?php if ($systemInfo['uptime'] !== null): ?>
          <?= $te('dashboard.uptime') ?>: <?= $e($systemInfo['uptime']['days']) ?>d <?= $e($systemInfo['uptime']['hours']) ?>h <?= $e($systemInfo['uptime']['minutes']) ?>m<br />
          <?php endif; ?>
          <?= $te('dashboard.load') ?>: <?= $e(implode(', ', array_map(fn($v) => number_format((float) $v, 2), $systemInfo['loadAverage']))) ?>
        </p>
      </div>
    </div>
    <div class="col-6">
      <div class="card">
        <header><h4><?= $te('dashboard.software') ?></h4></header>
        <p>
          iRedMail: <strong><?= $e($systemInfo['iredmailVersion']) ?></strong><br />
          PHP: <?= $e($systemInfo['phpVersion']) ?><br />
          iRedPanel: v<?= $e($systemInfo['iredpanelVersion']) ?>
        </p>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
