<?php $pageTitle = $t('throttle.view_title') . ': ' . $e($account); ?>
<div class="container">
  <h1><?= $te('throttle.settings_title') ?></h1>

  <div class="row breadcrumbs">
    <div class="col">
      <span class="text-light"><?= $e($account) ?></span>
    </div>
  </div>

  <?php if (!empty($error)): ?>
  <p class="text-error"><?= $e($error) ?></p>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
  <p class="text-success"><?= $e($success) ?></p>
  <?php endif; ?>

  <?php if (!empty($throttleSettings)): ?>
  <h3><?= $te('throttle.current_settings') ?></h3>
  <table class="striped">
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
        <td><?= $e($ts['kind'] ?? '') ?></td>
        <td><?= $e($ts['period'] ?? '') ?></td>
        <td><?= $e($ts['max_msgs'] ?? 0) ?></td>
        <td><?= $e($ts['max_quota'] ?? 0) ?></td>
        <td><?= $e($ts['msg_size'] ?? 0) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <p class="text-light"><?= $te('throttle.none_configured') ?></p>
  <?php endif; ?>

  <h3><?= $te('throttle.set_heading') ?></h3>
  <form method="post">
    <?= $csrfField ?>
    <div class="row">
      <div class="col-3">
        <label><?= $te('throttle.kind') ?></label>
        <select name="kind">
          <option value="outbound"><?= $te('throttle.outbound') ?></option>
          <option value="inbound"><?= $te('throttle.inbound') ?></option>
        </select>
      </div>
      <div class="col-3">
        <label><?= $te('throttle.period_seconds') ?></label>
        <input type="number" name="period" value="3600" min="0" />
      </div>
      <div class="col-3">
        <label><?= $te('throttle.max_messages') ?></label>
        <input type="number" name="maxMsgs" value="0" min="0" />
      </div>
      <div class="col-3">
        <label><?= $te('throttle.max_quota_bytes') ?></label>
        <input type="number" name="maxQuota" value="0" min="0" />
      </div>
    </div>
    <p>
      <button type="submit" class="button primary"><?= $te('throttle.save') ?></button>
    </p>
  </form>
</div>
