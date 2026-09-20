<?php
$pageTitle = $domain->domainName . ' - ' . $t('dns.title');
$mode = 'dns';
?>
<div class="page-header">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/domains"><?= $te('domain.list_title') ?></a></li>
        <li class="breadcrumb-item"><a href="/domains/<?= $e($domain->domainName) ?>/edit"><?= $e($domain->domainName) ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $te('dns.title') ?></li>
      </ol>
    </nav>
    <h1><?= $te('dns.title') ?></h1>
    <div class="small text-body-secondary mt-1"><?= $te('dns.intro') ?></div>
  </div>
  <div class="page-actions">
    <a href="/domains/<?= $e($domain->domainName) ?>/dns" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise me-1"></i><?= $te('dns.recheck') ?></a>
  </div>
</div>

<?php include __DIR__ . '/domainTabs.php'; ?>

<div class="card mt-3">
  <div class="table-responsive">
    <table class="table table-striped align-middle mb-0">
      <thead>
        <tr>
          <th><?= $te('dns.check') ?></th>
          <th><?= $te('common.status') ?></th>
          <th><?= $te('dns.records') ?></th>
          <th><?= $te('dns.result') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($dnsRows as $row): ?>
        <tr>
          <td class="text-nowrap">
            <?= $te('dns.check_' . $row->key) ?><?= $help('dns.check_' . $row->key) ?>
          </td>
          <td><span class="badge badge-status <?= $tone('dns_status', $row->status) ?>"><?= $te('dns.status_' . $row->status) ?></span></td>
          <td class="text-break">
            <?php foreach ($row->values as $value): ?>
            <div><code><?= $e($value) ?></code></div>
            <?php endforeach; ?>
            <?php if ($row->values === []): ?>
            <span class="text-body-secondary"><?= $te('common.na') ?></span>
            <?php endif; ?>
          </td>
          <td class="text-break"><?= $e($t('dns.' . $row->detail, $row->params)) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="alert alert-info mt-3">
  <?= $e($t('dns.dkim_note', ['name' => $dkimName])) ?>
</div>
