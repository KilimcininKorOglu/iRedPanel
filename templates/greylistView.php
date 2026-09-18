<?php $pageTitle = $t('greylist.view_title') . ': ' . $account; ?>
<div class="container">
  <?php $iredapdTab = 'greylist'; include __DIR__ . '/iredapdNav.php'; ?>
  <h1><?= $te('greylist.settings_title') ?></h1>

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

  <h3><?= $te('greylist.status_heading') ?></h3>
  <form method="post">
    <?= $csrfField ?>
    <input type="hidden" name="action" value="state" />
    <p>
      <label for="greylistState"><?= $te('greylist.state_for', ['account' => $account]) ?></label>
      <select id="greylistState" name="state">
        <?php if ($canInherit): ?>
        <option value="inherit"<?= $greylistState === 'inherit' ? ' selected' : '' ?>><?= $te('greylist.state_inherit') ?></option>
        <?php endif; ?>
        <option value="enabled"<?= $greylistState === 'enabled' ? ' selected' : '' ?>><?= $te('greylist.state_enabled') ?></option>
        <option value="disabled"<?= $greylistState === 'disabled' ? ' selected' : '' ?>><?= $te('greylist.state_disabled') ?></option>
      </select>
    </p>
    <p>
      <button type="submit" class="button primary"><?= $te('common.save') ?></button>
    </p>
  </form>

  <h3><?= $te('greylist.whitelist_heading') ?></h3>
  <form method="post">
    <?= $csrfField ?>
    <input type="hidden" name="action" value="whitelist" />
    <p>
      <label for="whitelistedSenders"><?= $te('greylist.senders_label') ?></label>
      <textarea id="whitelistedSenders" name="whitelistedSenders" rows="6"><?= $e(implode("\n", $whitelistedSenders ?? [])) ?></textarea>
    </p>
    <p>
      <button type="submit" class="button primary"><?= $te('greylist.save_whitelist') ?></button>
    </p>
  </form>
</div>
