<?php $pageTitle = !empty($showConfirmButton) ? $t('newsletter.confirm_action') : $t('newsletter.confirmed'); ?>
<div class="guest-card">
  <div class="card">
    <div class="card-body p-4">
      <h1 class="h4 mb-3"><?= $e($pageTitle) ?></h1>
      <p class="text-body-secondary"><?= $e($message) ?></p>
      <?php if (!empty($showConfirmButton)): ?>
      <form method="POST">
        <?= $csrfField ?>
        <button type="submit" class="btn btn-primary w-100"><?= $te('common.confirm') ?></button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
