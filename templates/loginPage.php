<?php
$pageTitle = $t('auth.title');
$fieldClass = !empty($error) ? 'form-control is-invalid' : 'form-control';
?>
<div class="guest-card">
  <div class="app-brand"><img src="<?= $e($brand['logoUrl'] ?? '/static/logo.svg') ?>" alt="" /> <?= $e($brand['name'] ?? 'iRedPanel') ?></div>
  <div class="card">
    <div class="card-body p-4">
      <h1 class="h4 mb-4"><?= $te('auth.title') ?></h1>
      <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= $e($error) ?></div>
      <?php endif; ?>
      <?php if (!empty($notice)): ?>
      <div class="alert alert-secondary"><?= $e($notice) ?></div>
      <?php endif; ?>
      <?php if (($failedAttempts ?? 0) > 0): ?>
      <div class="alert alert-warning"><?= $te('auth.failed_attempts', ['count' => (int) $failedAttempts]) ?></div>
      <?php endif; ?>

      <form method="post">
        <?= $csrfField ?>
        <input type="hidden" name="next" value="<?= $e($next) ?>" />
        <div class="mb-3">
          <label for="input__text" class="form-label"><?= $te('auth.email') ?></label><?= $help('auth.email') ?>
          <input id="input__text" type="text" name="email" class="<?= $fieldClass ?>" value="<?= $e($email ?? '') ?>" placeholder="<?= $te('auth.email_placeholder') ?>" autocomplete="username" autofocus />
        </div>
        <div class="mb-4">
          <label for="input__password" class="form-label"><?= $te('auth.password') ?></label><?= $help('auth.password') ?>
          <input id="input__password" type="password" name="password" class="<?= $fieldClass ?>" placeholder="<?= $te('auth.password_placeholder') ?>" autocomplete="current-password" />
        </div>
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right me-1"></i><?= $te('auth.sign_in') ?></button>
      </form>
      <?php if (!empty($features['passwordRecovery'])): ?>
      <div class="mt-3 text-center"><a href="/forgot-password"><?= $te('recovery.forgot_title') ?></a></div>
      <?php endif; ?>
    </div>
  </div>
</div>
