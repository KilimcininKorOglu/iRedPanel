<?php $pageTitle = $t('misc.error'); ?>
<div class="guest-card">
  <div class="card">
    <div class="card-body p-4">
      <h1 class="h4 mb-3"><i class="bi bi-exclamation-triangle text-danger me-2"></i><?= $te('misc.error') ?></h1>
      <p class="text-danger mb-0"><?= $e($message) ?></p>
    </div>
  </div>
</div>
