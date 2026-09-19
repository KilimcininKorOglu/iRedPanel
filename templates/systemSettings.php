<?php
$pageTitle = $t('sysset.title');
$onOff = fn(bool $v): string => $v ? $t('common.enabled') : $t('common.disabled');
$rows = [
    'sysset.backend' => '<code>' . $e($backend) . '</code>',
    'sysset.password_scheme' => '<code>' . $e($passwordScheme) . '</code>',
    'sysset.password_min_length' => $e($passwordMinLength),
    'sysset.pagination_per_page' => $e($paginationPerPage),
    'sysset.session_timeout' => $te('sysset.seconds', ['count' => $sessionTimeout]),
    'sysset.allowed_ip_ranges' => $e($allowedIpRanges !== '' ? $allowedIpRanges : $t('common.all')),
    'sysset.session_ip_validation' => $e($onOff($sessionValidateIp)),
    'sysset.update_check' => $e($onOff($checkUpdates)),
    'sysset.amavisd_integration' => $e($onOff($amavisdEnabled)),
    'sysset.fail2ban_integration' => $e($onOff($fail2banEnabled)),
    'sysset.iredapd_integration' => $e($onOff($iredapdEnabled)),
];
?>
<div class="page-header">
  <div>
    <h1><?= $te('sysset.title') ?></h1>
    <div class="small text-body-secondary mt-1"><?= $te('sysset.intro') ?></div>
  </div>
</div>

<div class="row g-4">
  <div class="col-xl-8">
    <div class="card mb-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr>
              <th><?= $te('sysset.setting') ?></th>
              <th><?= $te('sysset.value') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $labelKey => $valueHtml): ?>
            <tr>
              <td><?= $te($labelKey) ?></td>
              <td><?= $valueHtml ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="card">
      <div class="card-header"><?= $te('sysset.quick_links') ?></div>
      <div class="list-group list-group-flush">
        <a href="/last-logins" class="list-group-item list-group-item-action"><i class="bi bi-clock-history me-2"></i><?= $te('sysset.last_login_tracking') ?></a>
        <a href="/export/admins" class="list-group-item list-group-item-action"><i class="bi bi-filetype-csv me-2"></i><?= $te('sysset.export_csv') ?></a>
        <a href="/export/admins?format=json" class="list-group-item list-group-item-action"><i class="bi bi-filetype-json me-2"></i><?= $te('sysset.export_json') ?></a>
        <?php if ($backend === 'ldap'): ?>
        <a href="/export/ldif" class="list-group-item list-group-item-action">
          <i class="bi bi-filetype-txt me-2"></i><?= $te('export.ldif_tree') ?>
          <div class="small text-body-secondary"><?= $te('export.ldif_password_warning') ?></div>
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
