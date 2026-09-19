<?php
$pageTitle = $t('user.create');
$fieldClass = fn (string $field): string => 'form-control' . (!empty($validationErrors[$field]) ? ' is-invalid' : '');
?>
<div class="page-header">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/domains"><?= $e($domain) ?></a></li>
        <li class="breadcrumb-item"><a href="/<?= $e($domain) ?>/users"><?= $te('user.list_title') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $te('user.create') ?></li>
      </ol>
    </nav>
    <h1><?= $te('user.create') ?></h1>
  </div>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
<div class="alert alert-success"><?= $e($success) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-xl-7">
    <form method="post" autocomplete="off" class="card">
      <?= $csrfField ?>
      <div class="card-body">
        <div class="mb-3">
          <label for="uid" class="form-label"><?= $te('user.identifier') ?></label>
          <div class="input-group has-validation">
            <input id="uid" type="text" name="uid" required class="<?= $fieldClass('uid') ?>" value="<?= $e($user?->uid ?? '') ?>" />
            <span class="input-group-text">@<?= $e($domain) ?></span>
            <?php if (!empty($validationErrors['uid'])): ?>
            <div class="invalid-feedback"><?= $e($validationErrors['uid']) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label for="password" class="form-label"><?= $te('common.password') ?></label>
            <input name="password" type="password" id="password" required autocomplete="new-password" class="<?= $fieldClass('password') ?>" />
            <?php if (!empty($validationErrors['password'])): ?>
            <div class="invalid-feedback"><?= $e($validationErrors['password']) ?></div>
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <label for="password_repeat" class="form-label"><?= $te('user.password_repeat') ?></label>
            <input name="password_repeat" type="password" id="password_repeat" required class="<?= $fieldClass('password_repeat') ?>" />
            <?php if (!empty($validationErrors['password_repeat'])): ?>
            <div class="invalid-feedback"><?= $e($validationErrors['password_repeat']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary mb-3" data-generate-password><i class="bi bi-magic me-1"></i><?= $te('user.generate_password') ?></button>

        <div>
          <label for="mailQuota" class="form-label"><?= $te('user.quota_mb_label') ?></label>
          <input id="mailQuota" name="mailQuota" type="number" class="form-control" value="<?= $e($user?->mailQuota ?? $defaultQuota) ?>" required />
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $te('common.save') ?></button>
        <a href="/<?= $e($domain) ?>/users" class="btn btn-outline-secondary"><?= $te('common.cancel') ?></a>
      </div>
    </form>
  </div>
</div>
