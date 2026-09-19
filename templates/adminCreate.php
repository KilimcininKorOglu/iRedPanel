<?php
$pageTitle = $t('admin.create');
$fieldClass = fn (string $field): string => 'form-control' . (!empty($validationErrors[$field]) ? ' is-invalid' : '');
?>
<div class="page-header">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/admins"><?= $te('admin.list_title') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $te('common.create') ?></li>
      </ol>
    </nav>
    <h1><?= $te('admin.create') ?></h1>
  </div>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-xl-7">
    <form method="post" class="card">
      <?= $csrfField ?>
      <div class="card-body">
        <div class="mb-3">
          <label for="username" class="form-label"><?= $te('admin.email_address') ?></label>
          <input id="username" type="email" name="username" required placeholder="admin@example.com" class="<?= $fieldClass('username') ?>" value="<?= $e($admin?->username ?? '') ?>" />
          <?php if (!empty($validationErrors['username'])): ?>
          <div class="invalid-feedback"><?= $e($validationErrors['username']) ?></div>
          <?php endif; ?>
        </div>

        <div class="mb-3">
          <label for="name" class="form-label"><?= $te('admin.display_name') ?></label>
          <input id="name" type="text" name="name" class="form-control" value="<?= $e($admin?->name ?? '') ?>" />
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label for="password" class="form-label"><?= $te('common.password') ?></label>
            <input id="password" type="password" name="password" required autocomplete="new-password" class="<?= $fieldClass('password') ?>" />
            <?php if (!empty($validationErrors['password'])): ?>
            <div class="invalid-feedback"><?= $e($validationErrors['password']) ?></div>
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <label for="password_repeat" class="form-label"><?= $te('admin.repeat_password') ?></label>
            <input id="password_repeat" type="password" name="password_repeat" required class="<?= $fieldClass('password_repeat') ?>" />
            <?php if (!empty($validationErrors['password_repeat'])): ?>
            <div class="invalid-feedback"><?= $e($validationErrors['password_repeat']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary mb-3" data-generate-password><i class="bi bi-magic me-1"></i><?= $te('user.generate_password') ?></button>

        <div class="form-check form-switch mb-2">
          <input type="checkbox" class="form-check-input" id="isGlobalAdmin" name="isGlobalAdmin" <?php if ($admin === null || ($admin->isGlobalAdmin ?? false)): ?>checked<?php endif; ?> />
          <label class="form-check-label" for="isGlobalAdmin"><?= $te('admin.global_administrator') ?></label>
        </div>
        <div class="form-check form-switch">
          <input type="checkbox" class="form-check-input" id="active" name="active" <?php if ($admin === null || ($admin->active ?? true)): ?>checked<?php endif; ?> />
          <label class="form-check-label" for="active"><?= $te('common.active') ?></label>
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $te('admin.create') ?></button>
        <a href="/admins" class="btn btn-outline-secondary"><?= $te('common.cancel') ?></a>
      </div>
    </form>
  </div>
</div>
