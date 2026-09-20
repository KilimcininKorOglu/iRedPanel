<?php
$pageTitle = $t('domain.pref_personal_info');
// A stored code that the panel has no translation for stays selectable, so a save keeps it.
$languages = ['' => $t('user.language_default')] + $availableLocales + [(string) $user->language => (string) $user->language];
$cnLocked = in_array('cn', $lockedFields, true);
?>
<div class="page-header">
  <div>
    <h1><?= $te('domain.pref_personal_info') ?></h1>
    <div class="small text-body-secondary mt-1"><?= $e($session['email']) ?></div>
  </div>
</div>

<div class="row">
  <div class="col-xl-8">
    <?php if ($cnLocked): ?>
    <div class="alert alert-info"><?= $te('resource.managed_help') ?></div>
    <?php endif; ?>
    <form method="post" class="card">
      <?= $csrfField ?>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label for="cn" class="form-label"><?= $te('user.full_name') ?></label><?= $help('user.full_name') ?>
            <input id="cn" name="cn" type="text" class="form-control" value="<?= $e($user->cn) ?>"<?= $cnLocked ? ' readonly' : '' ?> />
          </div>
          <div class="col-md-6">
            <label for="language" class="form-label"><?= $te('user.language') ?></label><?= $help('user.language') ?>
            <select id="language" name="language" class="form-select">
              <?php foreach ($languages as $code => $name): ?>
              <option value="<?= $e($code) ?>"<?= (string) $code === (string) $user->language ? ' selected' : '' ?>><?= $e($name) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('common.save') ?></button>
      </div>
    </form>
  </div>
</div>
