<?php $pageTitle = ($action === 'subscribe' ? $t('newsletter.subscribe_to') : $t('newsletter.unsubscribe_from')) . ' ' . ($ml->name ?: $ml->address); ?>
<div class="guest-card">
  <div class="card">
    <div class="card-body p-4">
      <h1 class="h4 mb-3"><?= $e($pageTitle) ?></h1>

      <?php if (!empty($success)): ?>
      <div class="alert alert-success mb-0"><?= $e($success) ?></div>
      <?php endif; ?>
      <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= $e($error) ?></div>
      <?php endif; ?>

      <?php if (empty($success)): ?>
      <p class="text-body-secondary"><?= $action === 'subscribe' ? $te('newsletter.subscribe_hint') : $te('newsletter.unsubscribe_hint') ?></p>
      <form method="post">
        <?= $csrfField ?>
        <div class="mb-3">
          <label for="email" class="form-label"><?= $te('newsletter.email_address') ?></label><?= $help('newsletter.email_address') ?>
          <input type="email" id="email" name="email" class="form-control" required placeholder="your@email.com" value="<?= $e($email) ?>" />
        </div>
        <button type="submit" class="btn btn-primary w-100"><?= $action === 'subscribe' ? $te('newsletter.subscribe') : $te('newsletter.unsubscribe') ?></button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
