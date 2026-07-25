<?php $pageTitle = $t('auth.title'); ?>
<div class="login_form__container">
  <div class="card login_form__form">
    <header>
      <h4><?= $te('auth.title') ?></h4>
    </header>
    <form method="post">
      <?= $csrfField ?>
      <?php if (!empty($error)): ?>
      <p class="text-error"><?= $e($error) ?></p>
      <?php endif; ?>
      <?php if (($failedAttempts ?? 0) > 0): ?>
      <p class="text-error"><?= $te('auth.failed_attempts', ['count' => (int) $failedAttempts]) ?></p>
      <?php endif; ?>

      <input type="hidden" name="next" value="<?= $e($next) ?>" />

      <p>
        <label for="input__text"><?= $te('auth.email') ?></label>
        <input id="input__text" type="text" name="email" value="<?= $e($email ?? '') ?>"
          <?php if (!empty($error)): ?>class="error"<?php endif; ?> placeholder="<?= $te('auth.email_placeholder') ?>" />
      </p>
      <p>
        <label for="input__password"><?= $te('auth.password') ?></label>
        <input id="input__password" type="password" name="password"
          <?php if (!empty($error)): ?>class="error"<?php endif; ?> placeholder="<?= $te('auth.password_placeholder') ?>" />
      </p>
      <p><button type="submit"><?= $te('auth.sign_in') ?></button></p>
    </form>
  </div>
</div>
