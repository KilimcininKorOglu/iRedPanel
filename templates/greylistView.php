<?php $pageTitle = $t('greylist.view_title') . ': ' . $account; ?>
<div class="page-header">
  <div>
    <h1><?= $te('greylist.settings_title') ?></h1>
    <div class="small text-body-secondary mt-1"><?= $e($account) ?></div>
  </div>
</div>

<?php $iredapdTab = 'greylist'; include __DIR__ . '/iredapdNav.php'; ?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
<div class="alert alert-success"><?= $e($success) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-xl-8">
    <form method="post" class="card">
      <?= $csrfField ?>
      <input type="hidden" name="action" value="state" />
      <div class="card-header"><?= $te('greylist.status_heading') ?></div>
      <div class="card-body">
        <label for="greylistState" class="form-label"><?= $te('greylist.state_for', ['account' => $account]) ?></label>
        <select id="greylistState" name="state" class="form-select">
          <?php if ($canInherit): ?>
          <option value="inherit"<?= $greylistState === 'inherit' ? ' selected' : '' ?>><?= $te('greylist.state_inherit') ?></option>
          <?php endif; ?>
          <option value="enabled"<?= $greylistState === 'enabled' ? ' selected' : '' ?>><?= $te('greylist.state_enabled') ?></option>
          <option value="disabled"<?= $greylistState === 'disabled' ? ' selected' : '' ?>><?= $te('greylist.state_disabled') ?></option>
        </select>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('common.save') ?></button>
      </div>
    </form>

    <form method="post" class="card">
      <?= $csrfField ?>
      <input type="hidden" name="action" value="whitelist" />
      <div class="card-header"><?= $te('greylist.whitelist_heading') ?></div>
      <div class="card-body">
        <label for="whitelistedSenders" class="form-label"><?= $te('greylist.senders_label') ?></label>
        <textarea id="whitelistedSenders" name="whitelistedSenders" data-account-picker="multi" rows="6" class="form-control"><?= $e(implode("\n", $whitelistedSenders ?? [])) ?></textarea>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('greylist.save_whitelist') ?></button>
      </div>
    </form>
  </div>
</div>
