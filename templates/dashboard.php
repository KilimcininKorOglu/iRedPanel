<?php $pageTitle = 'Dashboard'; ?>
<div class="container">
  <h1>Dashboard</h1>

  <?php if (!empty($newVersion)): ?>
  <div class="card" style="border-left: 4px solid var(--color-primary, #1a73e8); margin-bottom: 1rem;">
    <p>A new version of iRedPanel is available: <strong>v<?= $e($newVersion) ?></strong>.
    <a href="https://github.com/KilimcininKorOglu/iRedPanel/releases/latest" target="_blank">View release</a></p>
  </div>
  <?php endif; ?>

  <div class="row">
    <div class="col-4">
      <div class="card">
        <header>
          <h4>Domains</h4>
        </header>
        <p>
          Total: <strong><?= $e($stats['totalDomains']) ?></strong><br />
          Active: <?= $e($stats['activeDomains']) ?><br />
          Disabled: <?= $e($stats['totalDomains'] - $stats['activeDomains']) ?>
        </p>
        <a href="/domains" class="button primary outline">Manage domains</a>
      </div>
    </div>

    <div class="col-4">
      <div class="card">
        <header>
          <h4>Users</h4>
        </header>
        <p>
          Total: <strong><?= $e($stats['totalUsers']) ?></strong><br />
          Active: <?= $e($stats['activeUsers']) ?><br />
          Disabled: <?= $e($stats['totalUsers'] - $stats['activeUsers']) ?>
        </p>
      </div>
    </div>

    <div class="col-4">
      <div class="card">
        <header>
          <h4>Admins</h4>
        </header>
        <p>
          Total: <strong><?= $e($stats['totalAdmins']) ?></strong>
        </p>
        <a href="/admins" class="button primary outline">Manage admins</a>
      </div>
    </div>
  </div>

  <div class="row" style="margin-top: 1rem;">
    <div class="col-6">
      <div class="card">
        <header>
          <h4>Quota</h4>
        </header>
        <p>
          Allocated: <strong><?= $e(number_format($stats['totalQuotaAllocated'])) ?> MB</strong><br />
          Used: <?= $e(number_format($stats['totalQuotaUsed'])) ?> MB
        </p>
      </div>
    </div>

    <div class="col-6">
      <div class="card">
        <header>
          <h4>Messages</h4>
        </header>
        <p>
          Total stored: <strong><?= $e(number_format($stats['totalMessages'])) ?></strong>
        </p>
      </div>
    </div>
  </div>

  <?php if (!empty($systemInfo)): ?>
  <div class="row" style="margin-top: 1rem;">
    <div class="col">
      <h2>System Information</h2>
    </div>
  </div>
  <div class="row">
    <div class="col-6">
      <div class="card">
        <header><h4>Server</h4></header>
        <p>
          Hostname: <strong><?= $e($systemInfo['hostname']) ?></strong><br />
          <?php if ($systemInfo['uptime'] !== null): ?>
          Uptime: <?= $e($systemInfo['uptime']['days']) ?>d <?= $e($systemInfo['uptime']['hours']) ?>h <?= $e($systemInfo['uptime']['minutes']) ?>m<br />
          <?php endif; ?>
          Load: <?= $e(implode(', ', array_map(fn($v) => number_format((float) $v, 2), $systemInfo['loadAverage']))) ?>
        </p>
      </div>
    </div>
    <div class="col-6">
      <div class="card">
        <header><h4>Software</h4></header>
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
