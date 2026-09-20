<?php $pageTitle = $t('recovery.reset_title'); ?>
<div class="guest-card">
  <div class="app-brand"><img src="<?= $e($brand['logoUrl'] ?? '/static/logo.svg') ?>" alt="" /> <?= $e($brand['name'] ?? 'iRedPanel') ?></div>
  <div class="card">
    <div class="card-body p-4">
      <h1 class="h4 mb-3"><?= $te('recovery.reset_title') ?></h1>

      <?php if ($done): ?>
      <div class="alert alert-success"><?= $te('recovery.msg_password_updated') ?></div>
      <?php else: ?>
      <?php if ($error !== null): ?>
      <div class="alert alert-danger"><?= $e($error) ?></div>
      <?php endif; ?>
      <p class="text-body-secondary"><?= $te('recovery.reset_hint', ['email' => $email]) ?></p>
      <form method="post">
        <?= $csrfField ?>
        <input type="text" class="d-none" autocomplete="username" value="<?= $e($email) ?>" readonly aria-hidden="true" tabindex="-1" />
        <div class="mb-3">
          <label for="password" class="form-label"><?= $te('user.new_password') ?></label><?= $help('user.new_password') ?>
          <input type="password" id="password" name="password" class="form-control" required autocomplete="new-password" />
        </div>
        <div class="mb-3">
          <label for="password_repeat" class="form-label"><?= $te('user.password_repeat') ?></label><?= $help('user.password_repeat') ?>
          <input type="password" id="password_repeat" name="password_repeat" class="form-control" required autocomplete="new-password" />
        </div>
        <button type="submit" class="btn btn-primary w-100"><?= $te('common.save') ?></button>
      </form>
      <?php endif; ?>
      <div class="mt-3"><a href="/login"><?= $te('recovery.back_to_login') ?></a></div>
    </div>
  </div>
</div>
