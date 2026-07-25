<?php $pageTitle = $t('greylist.view_title') . ': ' . $e($account); ?>
<div class="container">
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
    <input type="hidden" name="action" value="toggle" />
    <p>
      <label>
        <input type="checkbox" name="enabled" <?php if ($greylistEnabled): ?>checked<?php endif; ?> />
        <?= $te('greylist.enabled_for', ['account' => $account]) ?>
      </label>
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
