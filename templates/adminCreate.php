<?php
$pageTitle = $t('admin.create');
$fieldClass = fn (string $field): string => 'form-control' . (!empty($validationErrors[$field]) ? ' is-invalid' : '');
// A form that failed validation shows the posted values; a new form checks both boxes.
$value = fn (string $field, string $default = ''): string => is_string($posted[$field] ?? null) ? $posted[$field] : $default;
$checked = fn (string $field, bool $default): bool => $posted === null ? $default : isset($posted[$field]);
$limits = [
    'createMaxDomains' => 'admin.max_domains',
    'createMaxUsers' => 'admin.max_users',
    'createMaxAliases' => 'admin.max_aliases',
    'createMaxLists' => 'admin.max_lists',
    'createMaxQuota' => 'admin.max_quota',
];
$languages = ['' => $t('user.language_default')] + $availableLocales;
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
          <label for="username" class="form-label"><?= $te('admin.email_address') ?></label><?= $help('admin.email_address') ?>
          <input id="username" type="email" name="username" data-account-picker="single" data-types="user" required placeholder="admin@example.com" class="<?= $fieldClass('username') ?>" value="<?= $e($value('username')) ?>" />
          <?php if (!empty($validationErrors['username'])): ?>
          <div class="invalid-feedback"><?= $e($validationErrors['username']) ?></div>
          <?php endif; ?>
        </div>

        <div class="mb-3">
          <label for="name" class="form-label"><?= $te('admin.display_name') ?></label><?= $help('admin.display_name') ?>
          <input id="name" type="text" name="name" class="form-control" value="<?= $e($value('name')) ?>" />
        </div>

        <div class="mb-3">
          <label for="language" class="form-label"><?= $te('user.language') ?></label><?= $help('user.language') ?>
          <select id="language" name="language" class="form-select">
            <?php foreach ($languages as $code => $name): ?>
            <option value="<?= $e($code) ?>"<?= (string) $code === $value('language') ? ' selected' : '' ?>><?= $e($name) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label for="password" class="form-label"><?= $te('common.password') ?></label><?= $help('common.password') ?>
            <input id="password" type="password" name="password" required autocomplete="new-password" class="<?= $fieldClass('password') ?>" />
            <?php if (!empty($validationErrors['password'])): ?>
            <div class="invalid-feedback"><?= $e($validationErrors['password']) ?></div>
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <label for="password_repeat" class="form-label"><?= $te('admin.repeat_password') ?></label><?= $help('admin.repeat_password') ?>
            <input id="password_repeat" type="password" name="password_repeat" required class="<?= $fieldClass('password_repeat') ?>" />
            <?php if (!empty($validationErrors['password_repeat'])): ?>
            <div class="invalid-feedback"><?= $e($validationErrors['password_repeat']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary mb-3" data-generate-password><i class="bi bi-magic me-1"></i><?= $te('user.generate_password') ?></button>

        <div class="form-check form-switch mb-2">
          <input type="checkbox" class="form-check-input" id="isGlobalAdmin" name="isGlobalAdmin" <?= $checked('isGlobalAdmin', true) ? 'checked' : '' ?> />
          <label class="form-check-label" for="isGlobalAdmin"><?= $te('admin.global_administrator') ?></label><?= $help('admin.global_administrator') ?>
        </div>
        <div class="form-check form-switch">
          <input type="checkbox" class="form-check-input" id="active" name="active" <?= $checked('active', true) ? 'checked' : '' ?> />
          <label class="form-check-label" for="active"><?= $te('common.active') ?></label><?= $help('common.active') ?>
        </div>

        <h2 class="h6 mt-4"><?= $te('admin.resource_limits') ?></h2>
        <p class="text-body-secondary small"><?= $te('admin.resource_limits_hint') ?> <?= $te('admin.limits_not_global') ?></p>
        <div class="row g-3 mb-3">
          <?php foreach ($limits as $field => $labelKey): ?>
          <div class="col-md-4">
            <label for="<?= $field ?>" class="form-label"><?= $te($labelKey) ?></label><?= $help($labelKey) ?>
            <input type="number" id="<?= $field ?>" name="<?= $field ?>" min="-1" class="form-control" value="<?= $e($value($field, '-1')) ?>" />
          </div>
          <?php endforeach; ?>
        </div>
        <div class="form-check form-switch">
          <input type="checkbox" class="form-check-input" id="createNewDomains" name="createNewDomains" <?= $checked('createNewDomains', false) ? 'checked' : '' ?> />
          <label class="form-check-label" for="createNewDomains"><?= $te('admin.allow_domain_creation') ?></label><?= $help('admin.allow_domain_creation') ?>
        </div>
        <?php foreach (['disableViewingMailLog' => 'admin.disable_viewing_mail_log', 'disableManagingQuarantinedMails' => 'admin.disable_managing_quarantined_mails'] as $toggle => $labelKey): ?>
        <div class="form-check form-switch">
          <input type="checkbox" class="form-check-input" id="<?= $toggle ?>" name="<?= $toggle ?>" <?= $checked($toggle, false) ? 'checked' : '' ?> />
          <label class="form-check-label" for="<?= $toggle ?>"><?= $te($labelKey) ?></label><?= $help($labelKey) ?>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $te('admin.create') ?></button>
        <a href="/admins" class="btn btn-outline-secondary"><?= $te('common.cancel') ?></a>
      </div>
    </form>
  </div>
</div>
