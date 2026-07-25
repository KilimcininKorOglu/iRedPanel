<?php $pageTitle = !empty($showConfirmButton) ? $t('newsletter.confirm_action') : $t('newsletter.confirmed'); ?>
<div class="container">
  <div class="row">
    <div class="col-6">
      <h1><?= $e($pageTitle) ?></h1>
      <p><?= $e($message) ?></p>
      <?php if (!empty($showConfirmButton)): ?>
      <form method="POST">
        <button type="submit" class="button primary"><?= $te('common.confirm') ?></button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
