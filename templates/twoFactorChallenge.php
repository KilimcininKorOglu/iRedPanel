<?php $pageTitle = $t('totp.challenge_title'); ?>
<div class="guest-card">
  <div class="app-brand"><img src="<?= $e($brand['logoUrl'] ?? '/static/logo.svg') ?>" alt="" /> <?= $e($brand['name'] ?? 'iRedPanel') ?></div>
  <div class="card">
    <div class="card-body p-4">
      <h1 class="h4 mb-3"><?= $te('totp.challenge_title') ?></h1>
      <div class="small text-body-secondary mb-3"><?= $e($email) ?></div>

      <?php if ($error !== null): ?>
      <div class="alert alert-danger"><?= $e($error) ?></div>
      <?php endif; ?>

      <p class="text-body-secondary"><?= $te('totp.challenge_hint') ?></p>
      <form method="post">
        <?= $csrfField ?>
        <div class="mb-3">
          <label for="code" class="form-label"><?= $te('totp.code') ?></label><?= $help('totp.code') ?>
          <input type="text" id="code" name="code" class="form-control" inputmode="numeric" autocomplete="one-time-code" required autofocus />
        </div>
        <button type="submit" class="btn btn-primary w-100"><?= $te('totp.sign_in') ?></button>
      </form>
      <div class="mt-3"><a href="/login"><?= $te('recovery.back_to_login') ?></a></div>
    </div>
  </div>
</div>
