<?php $pageTitle = $t('recovery.forgot_title'); ?>
<div class="guest-card">
  <div class="app-brand"><img src="<?= $e($brand['logoUrl'] ?? '/static/logo-iredmail.png') ?>" alt="" /> <?= $e($brand['name'] ?? 'iRedPanel') ?></div>
  <div class="card">
    <div class="card-body p-4">
      <h1 class="h4 mb-3"><?= $te('recovery.forgot_title') ?></h1>

      <?php if ($sent): ?>
      <div class="alert alert-success"><?= $te('recovery.msg_link_sent', ['hours' => $expireHours]) ?></div>
      <?php else: ?>
      <p class="text-body-secondary"><?= $te('recovery.forgot_hint') ?></p>
      <form method="post">
        <?= $csrfField ?>
        <div class="mb-3">
          <label for="email" class="form-label"><?= $te('recovery.mailbox_address') ?></label><?= $help('recovery.mailbox_address') ?>
          <input type="email" id="email" name="email" class="form-control" required autofocus value="<?= $e($email) ?>" />
        </div>
        <button type="submit" class="btn btn-primary w-100"><?= $te('recovery.send_link') ?></button>
      </form>
      <?php endif; ?>
      <div class="mt-3"><a href="/login"><?= $te('recovery.back_to_login') ?></a></div>
    </div>
  </div>
</div>
