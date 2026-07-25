<?php $pageTitle = $t('wblist.senderscore_title'); ?>
<div class="container">
  <div class="row">
    <div class="col-8">
      <h1><?= $te('wblist.senderscore_title') ?></h1>
      <p class="text-light"><?= $te('wblist.senderscore_intro') ?></p>

      <?php if (!empty($success)): ?>
      <div class="card bg-success text-white"><?= $e($success) ?></div>
      <?php endif; ?>
      <?php if (!empty($error)): ?>
      <div class="card bg-error text-white"><?= $e($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <?= $csrfField ?>

        <fieldset>
          <legend><?= $te('wblist.senderscore_legend') ?></legend>
          <textarea name="ips" rows="10" placeholder="192.168.1.1&#10;10.0.0.1"><?= $e(implode("\n", $ips)) ?></textarea>
          <p class="text-light"><?= $te('wblist.senderscore_hint') ?></p>
        </fieldset>

        <button type="submit" class="button primary"><?= $te('common.save') ?></button>
      </form>
    </div>
  </div>
</div>
