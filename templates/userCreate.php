<?php
$pageTitle = $t('user.create');
$fieldClass = fn (string $field): string => 'form-control' . (!empty($validationErrors[$field]) ? ' is-invalid' : '');
// A form that failed validation shows the posted values, never a password or a password hash.
$value = fn (string $field): string => is_string($posted[$field] ?? null) ? $posted[$field] : '';
$languages = ['' => $t('user.language_default')] + $availableLocales;
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
          <label for="uid" class="form-label"><?= $te('user.identifier') ?></label><?= $help('user.identifier') ?>
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
            <label for="cn" class="form-label"><?= $te('user.full_name') ?></label><?= $help('user.full_name') ?>
            <input id="cn" type="text" name="cn" class="form-control" value="<?= $e($value('cn')) ?>" />
          </div>
          <div class="col-md-6">
            <label for="language" class="form-label"><?= $te('user.language') ?></label><?= $help('user.language') ?>
            <select id="language" name="language" class="form-select">
              <?php foreach ($languages as $code => $name): ?>
              <option value="<?= $e($code) ?>"<?= (string) $code === $value('language') ? ' selected' : '' ?>><?= $e($name) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label for="password" class="form-label"><?= $te('common.password') ?></label><?= $help('common.password') ?>
            <input name="password" type="password" id="password" autocomplete="new-password" class="<?= $fieldClass('password') ?>" />
            <?php if (!empty($validationErrors['password'])): ?>
            <div class="invalid-feedback"><?= $e($validationErrors['password']) ?></div>
            <?php endif; ?>
          </div>
          <div class="col-md-6">
            <label for="password_repeat" class="form-label"><?= $te('user.password_repeat') ?></label><?= $help('user.password_repeat') ?>
            <input name="password_repeat" type="password" id="password_repeat" class="<?= $fieldClass('password_repeat') ?>" />
            <?php if (!empty($validationErrors['password_repeat'])): ?>
            <div class="invalid-feedback"><?= $e($validationErrors['password_repeat']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary mb-3" data-generate-password><i class="bi bi-magic me-1"></i><?= $te('user.generate_password') ?></button>

        <div class="mb-3">
          <label for="passwordHash" class="form-label"><?= $te('user.password_hash') ?></label><?= $help('user.password_hash') ?>
          <input id="passwordHash" name="passwordHash" type="text" autocomplete="off" spellcheck="false" class="form-control font-monospace" placeholder="{SSHA512}..." />
        </div>

        <div class="mb-3">
          <label for="mailQuota" class="form-label"><?= $te('user.quota_mb_label') ?></label><?= $help('user.quota_mb_label') ?>
          <input id="mailQuota" name="mailQuota" type="number" class="form-control" value="<?= $e($user?->mailQuota ?? $defaultQuota) ?>" required />
        </div>

        <div class="row g-3<?= !empty($session['isGlobalAdmin']) ? ' mb-3' : '' ?>">
          <div class="col-md-6">
            <label for="mailboxFormat" class="form-label"><?= $te('user.mailbox_format') ?></label><?= $help('user.mailbox_format') ?>
            <select id="mailboxFormat" name="mailboxFormat" class="form-select">
              <?php foreach ($mailboxFormats as $format): ?>
              <option value="<?= $e($format) ?>"<?= $format === ($value('mailboxFormat') ?: 'maildir') ? ' selected' : '' ?>><?= $e($format) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label for="mailboxFolder" class="form-label"><?= $te('user.mailbox_folder') ?></label><?= $help('user.mailbox_folder') ?>
            <input id="mailboxFolder" name="mailboxFolder" type="text" maxlength="20" pattern="[a-zA-Z0-9]{1,20}" class="form-control" placeholder="Maildir" value="<?= $e($value('mailboxFolder')) ?>" />
          </div>
        </div>

        <?php if (!empty($session['isGlobalAdmin'])): ?>
        <div>
          <label for="maildir" class="form-label"><?= $te('user.maildir_path') ?></label><?= $help('user.maildir_path') ?>
          <input id="maildir" name="maildir" type="text" spellcheck="false" class="form-control font-monospace" placeholder="/var/vmail/vmail1/<?= $e($domain) ?>/..." value="<?= $e($value('maildir')) ?>" />
        </div>
        <?php endif; ?>
      </div>
      <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $te('common.save') ?></button>
        <a href="/<?= $e($domain) ?>/users" class="btn btn-outline-secondary"><?= $te('common.cancel') ?></a>
      </div>
    </form>
  </div>
</div>
