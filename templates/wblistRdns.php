<?php $pageTitle = $t('wblist.rdns_title'); ?>
<div class="container">
  <div class="row">
    <div class="col-8">
      <h1><?= $te('wblist.rdns_title') ?></h1>
      <p class="text-light"><?= $te('wblist.rdns_intro') ?></p>

      <?php if (!empty($success)): ?>
      <div class="card bg-success text-white"><?= $e($success) ?></div>
      <?php endif; ?>
      <?php if (!empty($error)): ?>
      <div class="card bg-error text-white"><?= $e($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <?= $csrfField ?>

        <fieldset>
          <legend><?= $te('wblist.rdns_whitelist_legend') ?></legend>
          <textarea name="whitelists" rows="8" placeholder="example.com&#10;trusted-relay.org"><?= $e(implode("\n", $whitelists)) ?></textarea>
          <p class="text-light"><?= $te('wblist.rdns_whitelist_hint') ?></p>
        </fieldset>

        <fieldset>
          <legend><?= $te('wblist.rdns_blacklist_legend') ?></legend>
          <textarea name="blacklists" rows="8" placeholder="spam-source.com"><?= $e(implode("\n", $blacklists)) ?></textarea>
          <p class="text-light"><?= $te('wblist.rdns_blacklist_hint') ?></p>
        </fieldset>

        <button type="submit" class="button primary"><?= $te('common.save') ?></button>
      </form>
    </div>
  </div>
</div>
