<?php
$pageTitle = $admin->username;
$adminPath = '/admins/' . $e($admin->username);
$fieldClass = fn (string $field): string => 'form-control' . (!empty($validationErrors[$field]) ? ' is-invalid' : '');
$tabs = [
    'general' => $t('admin.tab_general'),
    'password' => $t('common.password'),
    'domains' => $t('admin.tab_domains'),
    'limits' => $t('admin.tab_limits'),
];
$limits = [
    'createMaxDomains' => 'admin.max_domains',
    'createMaxUsers' => 'admin.max_users',
    'createMaxAliases' => 'admin.max_aliases',
    'createMaxLists' => 'admin.max_lists',
    'createMaxQuota' => 'admin.max_quota',
];
?>
<div class="page-header">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/admins"><?= $te('admin.list_title') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $e($admin->username) ?></li>
      </ol>
    </nav>
    <h1><?= $e($admin->username) ?></h1>
    <div class="small text-body-secondary mt-1">
      <?= $te('common.type') ?>: <?= $e($admin->isMailboxAdmin ? $t('admin.type_mailbox') : $t('admin.type_standalone')) ?> &middot;
      <?= $te('admin.created') ?>: <?= $e($admin->created ?? $t('common.na')) ?>
    </div>
  </div>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
<div class="alert alert-success"><?= $e($success) ?></div>
<?php endif; ?>

<ul class="nav nav-tabs">
  <?php foreach ($tabs as $key => $label): ?>
  <li class="nav-item"><a class="nav-link<?= $editMode === $key ? ' active' : '' ?>" href="<?= $adminPath ?>/<?= $key ?>"<?= $editMode === $key ? ' aria-current="page"' : '' ?>><?= $e($label) ?></a></li>
  <?php endforeach; ?>
</ul>

<div class="row">
  <div class="col-xl-8">
    <?php if ($editMode === 'general'): ?>
    <form method="post" class="card">
      <?= $csrfField ?>
      <div class="card-body">
        <div class="mb-3">
          <label for="name" class="form-label"><?= $te('admin.display_name') ?></label><?= $help('admin.display_name') ?>
          <input id="name" type="text" name="name" class="form-control" value="<?= $e($admin->name) ?>" />
        </div>
        <div class="mb-3">
          <label for="language" class="form-label"><?= $te('user.language') ?></label><?= $help('user.language') ?>
          <?php $languages = ['' => $t('user.language_default')] + $availableLocales + [$admin->language => $admin->language]; ?>
          <select id="language" name="language" class="form-select">
            <?php foreach ($languages as $code => $name): ?>
            <option value="<?= $e($code) ?>"<?= (string) $code === $admin->language ? ' selected' : '' ?>><?= $e($name) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-check form-switch mb-2">
          <input type="checkbox" class="form-check-input" id="isGlobalAdmin" name="isGlobalAdmin" <?php if ($admin->isGlobalAdmin): ?>checked<?php endif; ?> />
          <label class="form-check-label" for="isGlobalAdmin"><?= $te('admin.global_administrator') ?></label><?= $help('admin.global_administrator') ?>
        </div>
        <div class="form-check form-switch">
          <input type="checkbox" class="form-check-input" id="active" name="active" <?php if ($admin->active): ?>checked<?php endif; ?> />
          <label class="form-check-label" for="active"><?= $te('common.active') ?></label><?= $help('common.active') ?>
        </div>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('common.save_changes') ?></button>
      </div>
    </form>

    <?php elseif ($editMode === 'password'): ?>
    <form method="post" class="card">
      <?= $csrfField ?>
      <div class="card-body">
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label for="password" class="form-label"><?= $te('user.new_password') ?></label><?= $help('user.new_password') ?>
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
        <button type="button" class="btn btn-sm btn-outline-secondary" data-generate-password><i class="bi bi-magic me-1"></i><?= $te('user.generate_password') ?></button>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('user.change_password') ?></button>
      </div>
    </form>

    <?php elseif ($editMode === 'domains'): ?>
    <div class="card">
      <div class="card-header"><?= $te('admin.tab_domains') ?></div>
      <?php if (!empty($managedDomains)): ?>
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr>
              <th><?= $te('common.domain') ?></th>
              <th class="text-end"><?= $te('common.action') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($managedDomains as $managedDomain): ?>
            <tr>
              <td><?= $e($managedDomain) ?></td>
              <td>
                <form method="post" class="table-actions">
                  <?= $csrfField ?>
                  <input type="hidden" name="action" value="revoke" />
                  <input type="hidden" name="domain" value="<?= $e($managedDomain) ?>" />
                  <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg me-1"></i><?= $te('admin.revoke') ?></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="card-body text-body-secondary"><?= $te('admin.no_domains_assigned') ?></div>
      <?php endif; ?>
    </div>

    <form method="post" class="card">
      <?= $csrfField ?>
      <input type="hidden" name="action" value="assign" />
      <div class="card-header"><?= $te('admin.assign_domain') ?></div><?= $help('admin.assign_domain') ?>
      <div class="card-body d-flex flex-wrap gap-2">
        <select name="domain" class="form-select flex-grow-1 w-auto" aria-label="<?= $te('common.domain') ?>">
          <?php foreach ($allDomainNames as $domainName): ?>
            <?php if (!in_array($domainName, $managedDomains, true)): ?>
            <option value="<?= $e($domainName) ?>"><?= $e($domainName) ?></option>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary"><?= $te('admin.assign') ?></button>
      </div>
    </form>

    <?php elseif ($editMode === 'limits'): ?>
    <form method="post" class="card">
      <?= $csrfField ?>
      <div class="card-header"><?= $te('admin.resource_limits') ?></div>
      <div class="card-body">
        <p class="text-body-secondary"><?= $te('admin.resource_limits_hint') ?> <?= $te('admin.limits_not_global') ?></p>
        <div class="row g-3 mb-3">
          <?php foreach ($limits as $field => $labelKey): ?>
          <div class="col-md-4">
            <label for="<?= $field ?>" class="form-label"><?= $te($labelKey) ?></label><?= $help($labelKey) ?>
            <input type="number" id="<?= $field ?>" name="<?= $field ?>" min="-1" class="form-control" value="<?= $e($admin->$field) ?>" />
          </div>
          <?php endforeach; ?>
        </div>
        <div class="form-check form-switch">
          <input type="checkbox" class="form-check-input" id="createNewDomains" name="createNewDomains" <?= $admin->createNewDomains ? 'checked' : '' ?> />
          <label class="form-check-label" for="createNewDomains"><?= $te('admin.allow_domain_creation') ?></label><?= $help('admin.allow_domain_creation') ?>
        </div>
        <?php foreach (['disableViewingMailLog' => 'admin.disable_viewing_mail_log', 'disableManagingQuarantinedMails' => 'admin.disable_managing_quarantined_mails'] as $toggle => $labelKey): ?>
        <div class="form-check form-switch">
          <input type="checkbox" class="form-check-input" id="<?= $toggle ?>" name="<?= $toggle ?>" <?= $admin->{$toggle} ? 'checked' : '' ?> />
          <label class="form-check-label" for="<?= $toggle ?>"><?= $te($labelKey) ?></label><?= $help($labelKey) ?>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('admin.save_limits') ?></button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>
