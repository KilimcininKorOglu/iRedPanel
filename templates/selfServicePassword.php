<?php $pageTitle = $t('user.change_password'); ?>
<div class="page-header">
  <div>
    <h1><?= $te('user.change_password') ?></h1>
    <div class="small text-body-secondary mt-1"><?= $e($session['email']) ?></div>
  </div>
</div>

<div class="row">
  <div class="col-xl-8">
    <form method="post" class="card">
      <?= $csrfField ?>
      <?php // Password managers need the account name to store the new password. ?>
      <input type="text" class="d-none" autocomplete="username" value="<?= $e($session['email']) ?>" readonly aria-hidden="true" tabindex="-1" />
      <div class="card-body">
        <div class="mb-3">
          <label for="old_password" class="form-label"><?= $te('user.current_password') ?></label><?= $help('user.current_password') ?>
          <input name="old_password" type="password" id="old_password" required autocomplete="current-password" class="form-control" />
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label for="password" class="form-label"><?= $te('user.new_password') ?></label><?= $help('user.new_password') ?>
            <input name="password" type="password" id="password" required autocomplete="new-password" class="form-control" />
          </div>
          <div class="col-md-6">
            <label for="password_repeat" class="form-label"><?= $te('user.password_repeat') ?></label><?= $help('user.password_repeat') ?>
            <input name="password_repeat" type="password" id="password_repeat" required autocomplete="new-password" class="form-control" />
          </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-generate-password><i class="bi bi-magic me-1"></i><?= $te('user.generate_password') ?></button>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('common.save') ?></button>
      </div>
    </form>
  </div>
</div>
