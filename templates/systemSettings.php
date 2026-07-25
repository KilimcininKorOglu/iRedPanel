<?php
$pageTitle = $t('sysset.title');
$onOff = fn(bool $v): string => $v ? $t('common.enabled') : $t('common.disabled');
?>
<div class="container">
  <div class="row">
    <div class="col-8">
      <h1><?= $te('sysset.title') ?></h1>

      <p class="text-light"><?= $te('sysset.intro') ?></p>

      <table class="striped">
        <thead>
          <tr>
            <th><?= $te('sysset.setting') ?></th>
            <th><?= $te('sysset.value') ?></th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><?= $te('sysset.backend') ?></td>
            <td><code><?= $e($backend) ?></code></td>
          </tr>
          <tr>
            <td><?= $te('sysset.password_scheme') ?></td>
            <td><code><?= $e($passwordScheme) ?></code></td>
          </tr>
          <tr>
            <td><?= $te('sysset.password_min_length') ?></td>
            <td><?= $e($passwordMinLength) ?></td>
          </tr>
          <tr>
            <td><?= $te('sysset.pagination_per_page') ?></td>
            <td><?= $e($paginationPerPage) ?></td>
          </tr>
          <tr>
            <td><?= $te('sysset.session_timeout') ?></td>
            <td><?= $te('sysset.seconds', ['count' => $sessionTimeout]) ?></td>
          </tr>
          <tr>
            <td><?= $te('sysset.allowed_ip_ranges') ?></td>
            <td><?= $e($allowedIpRanges !== '' ? $allowedIpRanges : $t('common.all')) ?></td>
          </tr>
          <tr>
            <td><?= $te('sysset.session_ip_validation') ?></td>
            <td><?= $e($onOff($sessionValidateIp)) ?></td>
          </tr>
          <tr>
            <td><?= $te('sysset.update_check') ?></td>
            <td><?= $e($onOff($checkUpdates)) ?></td>
          </tr>
          <tr>
            <td><?= $te('sysset.amavisd_integration') ?></td>
            <td><?= $e($onOff($amavisdEnabled)) ?></td>
          </tr>
          <tr>
            <td><?= $te('sysset.fail2ban_integration') ?></td>
            <td><?= $e($onOff($fail2banEnabled)) ?></td>
          </tr>
          <tr>
            <td><?= $te('sysset.iredapd_integration') ?></td>
            <td><?= $e($onOff($iredapdEnabled)) ?></td>
          </tr>
        </tbody>
      </table>

      <h3><?= $te('sysset.quick_links') ?></h3>
      <ul>
        <li><a href="/last-logins"><?= $te('sysset.last_login_tracking') ?></a></li>
        <li><a href="/export/admins"><?= $te('sysset.export_csv') ?></a></li>
        <li><a href="/export/admins?format=json"><?= $te('sysset.export_json') ?></a></li>
      </ul>
    </div>
  </div>
</div>
