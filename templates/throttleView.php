<?php
$pageTitle = $t('throttle.view_title') . ': ' . $account;
$limitFields = [
    'maxMsgs' => 'throttle.max_messages',
    'maxQuota' => 'throttle.max_quota_bytes',
    'msgSize' => 'throttle.max_message_size',
];
?>
<div class="page-header">
  <div>
    <h1><?= $te('throttle.settings_title') ?></h1>
    <div class="small text-body-secondary mt-1"><?= $e($account) ?></div>
  </div>
</div>

<?php $iredapdTab = 'throttle'; include __DIR__ . '/iredapdNav.php'; ?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
<div class="alert alert-success"><?= $e($success) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-xl-9">
    <div class="card">
      <div class="card-header"><?= $te('throttle.current_settings') ?></div>
      <?php if (!empty($throttleSettings)): ?>
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr>
              <th><?= $te('throttle.kind') ?></th>
              <th><?= $te('throttle.period_sec') ?></th>
              <th><?= $te('throttle.max_messages') ?></th>
              <th><?= $te('throttle.max_quota_bytes') ?></th>
              <th><?= $te('throttle.max_message_size') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($throttleSettings as $ts): ?>
            <tr>
              <td><span class="badge badge-status <?= $tone('throttle_kind', (string) ($ts['kind'] ?? '')) ?>"><?= $e($ts['kind'] ?? '') ?></span></td>
              <td><?= $e($ts['period'] ?? '') ?></td>
              <td><?= $e($ts['max_msgs'] ?? 0) ?></td>
              <td><?= $e($ts['max_quota'] ?? 0) ?></td>
              <td><?= $e($ts['msg_size'] ?? 0) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="card-body text-body-secondary"><?= $te('throttle.none_configured') ?></div>
      <?php endif; ?>
    </div>

    <form method="post" class="card">
      <?= $csrfField ?>
      <div class="card-header"><?= $te('throttle.set_heading') ?></div>
      <div class="card-body">
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label for="throttleKind" class="form-label"><?= $te('throttle.kind') ?></label><?= $help('throttle.kind') ?>
            <select id="throttleKind" name="kind" class="form-select">
              <option value="outbound"><?= $te('throttle.outbound') ?></option>
              <option value="inbound"><?= $te('throttle.inbound') ?></option>
              <option value="external"><?= $te('throttle.external') ?></option>
            </select>
          </div>
          <div class="col-md-6">
            <label for="throttlePeriod" class="form-label"><?= $te('throttle.period_seconds') ?></label><?= $help('throttle.period_seconds') ?>
            <input id="throttlePeriod" type="number" name="period" class="form-control" value="3600" min="1" required />
          </div>
          <?php foreach ($limitFields as $field => $labelKey): ?>
          <div class="col-md-4">
            <label for="<?= $field ?>" class="form-label"><?= $te($labelKey) ?></label><?= $help($labelKey) ?>
            <input id="<?= $field ?>" type="number" name="<?= $field ?>" class="form-control" value="-1" min="-1" />
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('throttle.save') ?></button>
      </div>
    </form>
  </div>
</div>
