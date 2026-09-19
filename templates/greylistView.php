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
        <label for="greylistState" class="form-label"><?= $te('greylist.state_for', ['account' => $account]) ?></label><?= $help('greylist.state_for', ['account' => $account]) ?>
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
        <label for="whitelistedSenders" class="form-label"><?= $te('greylist.senders_label') ?></label><?= $help('greylist.senders_label') ?>
        <textarea id="whitelistedSenders" name="whitelistedSenders" data-account-picker="multi" rows="6" class="form-control"><?= $e(implode("\n", $whitelistedSenders ?? [])) ?></textarea>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('greylist.save_whitelist') ?></button>
      </div>
    </form>

    <div class="card">
      <div class="card-header"><?= $te('greylist.accounts_heading') ?></div>
      <?php if (!empty($greylistAccounts)): ?>
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr>
              <th><?= $te('spampolicy.account') ?></th>
              <th><?= $te('common.status') ?></th>
              <th class="text-end"><?= $te('common.actions') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($greylistAccounts as $row): ?>
            <tr>
              <td><a href="/iredapd/greylist/<?= $e(rawurlencode($row['account'])) ?>"><?= $e($row['account']) ?></a></td>
              <td><?= $te($row['active'] ? 'greylist.state_enabled' : 'greylist.state_disabled') ?></td>
              <td>
                <form method="post" class="table-actions" data-confirm="<?= $te('greylist.remove_settings_confirm') ?>">
                  <?= $csrfField ?>
                  <input type="hidden" name="action" value="deleteSettings" />
                  <input type="hidden" name="target" value="<?= $e($row['account']) ?>" />
                  <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg me-1"></i><?= $te('greylist.remove_settings') ?></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="card-body text-body-secondary"><?= $te('greylist.no_accounts') ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>
