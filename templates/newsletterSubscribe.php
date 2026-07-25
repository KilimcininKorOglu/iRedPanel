<?php $pageTitle = ($action === 'subscribe' ? $t('newsletter.subscribe_to') : $t('newsletter.unsubscribe_from')) . ' ' . ($ml->name ?: $ml->address); ?>
<div class="container">
  <div class="row">
    <div class="col-6">
      <h1><?= $e($pageTitle) ?></h1>

      <?php if (!empty($success)): ?>
      <div class="card bg-success text-white"><?= $e($success) ?></div>
      <?php endif; ?>
      <?php if (!empty($error)): ?>
      <div class="card bg-error text-white"><?= $e($error) ?></div>
      <?php endif; ?>

      <?php if (empty($success)): ?>
      <p><?= $action === 'subscribe' ? $te('newsletter.subscribe_hint') : $te('newsletter.unsubscribe_hint') ?></p>

      <form method="post">
        <label for="email"><?= $te('newsletter.email_address') ?></label>
        <input type="email" id="email" name="email" required placeholder="your@email.com" />

        <button type="submit" class="button primary"><?= $action === 'subscribe' ? $te('newsletter.subscribe') : $te('newsletter.unsubscribe') ?></button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
