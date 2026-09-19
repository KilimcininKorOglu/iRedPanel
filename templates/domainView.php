<?php
$pageTitle = $domain->domainName;
$mode = $editMode ?? 'general';
$domainPath = '/domains/' . $e($domain->domainName);
$tabs = [
    'general' => [$domainPath . '/edit', $t('admin.tab_general')],
    'settings' => [$domainPath . '/settings', $t('common.settings')],
    'catchall' => [$domainPath . '/catchall', $t('domain.tab_catchall')],
    'bcc' => [$domainPath . '/bcc', $t('domain.tab_bcc')],
    'relay' => [$domainPath . '/relay', $t('domain.tab_relay')],
    'admins' => [$domainPath . '/admins', $t('domain.tab_admins')],
];
// A domain admin sees only the pages that the global admin left open.
$tabs = array_intersect_key($tabs, array_flip($openPages));
$domainProfileLabels = array_combine(\App\Models\ProfileToggles::DOMAIN_PROFILES, [$t('common.settings'), $t('domain.tab_catchall'), $t('domain.tab_bcc'), $t('domain.tab_relay')]);
$userProfileLabels = array_combine(\App\Models\ProfileToggles::USER_PROFILES, [$t('user.tab_general'), $t('common.password'), $t('user.tab_services'), $t('user.tab_forwarding'), $t('user.tab_aliases'), $t('domain.tab_bcc'), $t('domain.tab_relay')]);
$preferenceLabels = array_combine(\App\Models\ProfileToggles::USER_PREFERENCES, [$t('domain.pref_personal_info'), $t('common.password'), $t('user.tab_forwarding'), $t('domain.pref_wblist'), $t('domain.pref_spampolicy'), $t('domain.pref_quarantine'), $t('domain.pref_received')]);
$toggleList = function (string $name, array $labels, array $checked) use ($e): string {
    $html = '<div class="row g-2">';
    foreach ($labels as $value => $label) {
        $id = $e($name . '-' . $value);
        $html .= '<div class="col-md-6"><div class="form-check">'
            . '<input type="checkbox" class="form-check-input" id="' . $id . '" name="' . $e($name) . '[]" value="' . $e($value) . '"' . (in_array($value, $checked, true) ? ' checked' : '') . ' />'
            . '<label class="form-check-label" for="' . $id . '">' . $e($label) . '</label></div></div>';
    }
    return $html . '</div>';
};
?>
<div class="page-header">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/domains"><?= $te('domain.list_title') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $e($domain->domainName) ?></li>
      </ol>
    </nav>
    <h1><?= $e($domain->domainName) ?></h1>
    <div class="small text-body-secondary mt-1">
      <?= $te('domain.users') ?>: <?= $e($domain->currentUserCount) ?> &middot;
      <?= $te('admin.created') ?>: <?= $e($domain->created ?? $t('common.na')) ?>
    </div>
  </div>
  <div class="page-actions">
    <a href="/<?= $e($domain->domainName) ?>/users" class="btn btn-outline-secondary"><i class="bi bi-people me-1"></i><?= $te('domain.users') ?></a>
  </div>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
<div class="alert alert-success"><?= $e($success) ?></div>
<?php endif; ?>

<ul class="nav nav-tabs">
  <?php foreach ($tabs as $key => [$href, $label]): ?>
  <li class="nav-item"><a class="nav-link<?= $mode === $key ? ' active' : '' ?>" href="<?= $href ?>"<?= $mode === $key ? ' aria-current="page"' : '' ?>><?= $e($label) ?></a></li>
  <?php endforeach; ?>
  <?php if (!empty($features['iredapd']) && !empty($session['isGlobalAdmin'])): ?>
  <li class="nav-item"><a class="nav-link" href="/iredapd/throttle/@<?= $e($domain->domainName) ?>"><?= $te('throttle.view_title') ?></a></li>
  <li class="nav-item"><a class="nav-link" href="/iredapd/greylist/@<?= $e($domain->domainName) ?>"><?= $te('greylist.view_title') ?></a></li>
  <?php endif; ?>
</ul>

<div class="row">
  <div class="col-xl-8">
    <?php if ($mode === 'general'): ?>
    <form method="post" class="card">
      <?= $csrfField ?>
      <div class="card-body">
        <div class="mb-3">
          <label for="description" class="form-label"><?= $te('common.description') ?></label>
          <input id="description" type="text" name="description" class="form-control" value="<?= $e($domain->description) ?>" />
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label for="maxQuota" class="form-label"><?= $te('domain.max_quota') ?></label>
            <input id="maxQuota" type="number" name="maxQuota" min="0" class="form-control" value="<?= $e($domain->maxQuota) ?>" />
          </div>
          <?php if ($supportsDomainQuota): ?>
          <div class="col-md-6">
            <label for="quota" class="form-label"><?= $te('domain.domain_quota') ?></label>
            <input id="quota" type="number" name="quota" min="0" class="form-control" value="<?= $e($domain->quota) ?>" />
          </div>
          <?php endif; ?>
          <div class="col-md-6">
            <label for="mailboxes" class="form-label"><?= $te('domain.max_mailboxes') ?></label>
            <input id="mailboxes" type="number" name="mailboxes" min="0" class="form-control" value="<?= $e($domain->mailboxes) ?>" />
          </div>
          <div class="col-md-6">
            <label for="aliases" class="form-label"><?= $te('domain.max_aliases') ?></label>
            <input id="aliases" type="number" name="aliases" min="0" class="form-control" value="<?= $e($domain->aliases) ?>" />
          </div>
          <div class="col-md-6">
            <label for="lists" class="form-label"><?= $te('domain.max_lists') ?></label>
            <input id="lists" type="number" name="lists" min="0" class="form-control" value="<?= $e($domain->lists) ?>" />
          </div>
        </div>

        <div class="mb-3">
          <label for="transport" class="form-label"><?= $te('domain.transport') ?></label>
          <?php
          // Postfix transports of iRedMail; a stored custom transport stays selectable so a save keeps it.
          $transports = ['dovecot' => 'dovecot', 'lmtp:unix:private/dovecot-lmtp' => 'lmtp'];
          if (!isset($transports[$domain->transport])) {
              $transports[$domain->transport] = $domain->transport;
          }
          ?>
          <select id="transport" name="transport" class="form-select">
            <?php foreach ($transports as $value => $label): ?>
            <option value="<?= $e($value) ?>" <?php if ($domain->transport === $value): ?>selected<?php endif; ?>><?= $e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <fieldset class="border rounded p-3 mb-3">
          <legend class="float-none w-auto px-2 fs-6 mb-0"><?= $te('domain.backup_mx') ?></legend>
          <div class="form-check form-switch mb-2">
            <input type="checkbox" class="form-check-input" id="backupMx" name="backupMx" <?php if ($domain->backupMx): ?>checked<?php endif; ?> />
            <label class="form-check-label" for="backupMx"><?= $te('domain.backup_mx_enable') ?></label>
          </div>
          <label for="primaryMx" class="form-label"><?= $te('domain.primary_mx') ?></label>
          <input id="primaryMx" type="text" name="primaryMx" class="form-control" value="<?= $e($domain->primaryMx) ?>" placeholder="[mx1.example.com]:25" />
          <div class="form-text"><?= $te('domain.backup_mx_help') ?></div>
        </fieldset>

        <div class="form-check form-switch">
          <input type="checkbox" class="form-check-input" id="active" name="active" <?php if ($domain->active): ?>checked<?php endif; ?> />
          <label class="form-check-label" for="active"><?= $te('common.active') ?></label>
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $te('common.save_changes') ?></button>
        <a href="/domains" class="btn btn-outline-secondary"><?= $te('domain.back') ?></a>
      </div>
    </form>

    <?php elseif ($mode === 'settings'): ?>
    <form method="post" class="card">
      <?= $csrfField ?>
      <div class="card-body">
        <div class="mb-3">
          <label for="defaultUserQuota" class="form-label"><?= $te('domain.default_user_quota') ?></label>
          <input id="defaultUserQuota" type="number" name="defaultUserQuota" min="0" class="form-control" value="<?= $e($domainSettings->defaultUserQuota ?? 0) ?>" />
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label for="minPasswordLength" class="form-label"><?= $te('domain.min_password_length') ?></label>
            <input id="minPasswordLength" type="number" name="minPasswordLength" min="0" class="form-control" value="<?= $e($domainSettings->minPasswordLength ?? 0) ?>" />
          </div>
          <div class="col-md-6">
            <label for="maxPasswordLength" class="form-label"><?= $te('domain.max_password_length') ?></label>
            <input id="maxPasswordLength" type="number" name="maxPasswordLength" min="0" class="form-control" value="<?= $e($domainSettings->maxPasswordLength ?? 0) ?>" />
          </div>
        </div>

        <fieldset class="mb-3">
          <legend class="form-label fs-6"><?= $te('domain.disabled_services') ?></legend>
          <div class="row g-2">
            <?php foreach (\App\Models\User::SERVICE_TOGGLES as $service => $toggle): ?>
            <div class="col-md-6">
              <div class="form-check">
                <input type="checkbox" class="form-check-input" id="disabled-<?= $e($service) ?>" name="disabledMailServices[]" value="<?= $e($service) ?>" <?php if (in_array($service, $domainSettings->disabledMailServices, true)): ?>checked<?php endif; ?> />
                <label class="form-check-label" for="disabled-<?= $e($service) ?>"><?= $e(\App\Models\User::SERVICE_LABELS[$toggle]) ?></label>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="form-text"><?= $te('domain.disabled_services_help') ?></div>
        </fieldset>

        <fieldset class="mb-3">
          <legend class="form-label fs-6"><?= $te('domain.self_service') ?></legend>
          <div class="form-check form-switch mb-2">
            <input type="checkbox" class="form-check-input" id="selfService" name="selfService" value="1" <?php if ($domainSettings->selfService()): ?>checked<?php endif; ?> />
            <label class="form-check-label" for="selfService"><?= $te('domain.self_service_enable') ?></label>
          </div>
          <div class="form-text mb-2"><?= $te('domain.disabled_preferences_help') ?></div>
          <?= $toggleList('disabledUserPreferences', $preferenceLabels, $domainSettings->disabledUserPreferences) ?>
        </fieldset>

        <?php if ($isGlobalAdmin): ?>
        <fieldset class="mb-3">
          <legend class="form-label fs-6"><?= $te('domain.disabled_domain_profiles') ?></legend>
          <?= $toggleList('disabledDomainProfiles', $domainProfileLabels, $domainSettings->disabledDomainProfiles) ?>
          <div class="form-text"><?= $te('domain.disabled_domain_profiles_help') ?></div>
        </fieldset>

        <fieldset class="mb-3">
          <legend class="form-label fs-6"><?= $te('domain.disabled_user_profiles') ?></legend>
          <?= $toggleList('disabledUserProfiles', $userProfileLabels, $domainSettings->disabledUserProfiles) ?>
          <div class="form-text"><?= $te('domain.disabled_user_profiles_help') ?></div>
        </fieldset>
        <?php endif; ?>

        <div>
          <label for="disclaimer" class="form-label"><?= $te('domain.disclaimer_text') ?></label>
          <textarea id="disclaimer" name="disclaimer" rows="5" class="form-control"><?= $e($domain->disclaimer) ?></textarea>
        </div>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('mlist.save_settings') ?></button>
      </div>
    </form>

    <?php elseif ($mode === 'catchall'): ?>
    <form method="post" class="card">
      <?= $csrfField ?>
      <div class="card-header"><?= $te('domain.catchall_address') ?></div>
      <div class="card-body">
        <p class="text-body-secondary"><?= $te('domain.catchall_desc') ?></p>
        <label for="catchallTarget" class="form-label"><?= $te('domain.forward_to') ?></label>
        <input id="catchallTarget" type="email" name="catchallTarget" data-account-picker="single" class="form-control"
          value="<?= $e($catchallTarget ?? '') ?>"
          placeholder="<?= $te('domain.catchall_placeholder') ?>" />
        <div class="form-text"><?= $te('domain.catchall_remove_hint') ?></div>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('domain.save_catchall') ?></button>
      </div>
    </form>

    <?php elseif ($mode === 'bcc'): ?>
    <form method="post" class="card">
      <?= $csrfField ?>
      <div class="card-header"><?= $te('domain.bcc_settings') ?></div>
      <div class="card-body">
        <p class="text-body-secondary"><?= $te('domain.bcc_desc') ?></p>
        <div class="mb-3">
          <label for="senderBcc" class="form-label"><?= $te('domain.sender_bcc') ?></label>
          <input id="senderBcc" type="email" name="senderBcc" data-account-picker="single" class="form-control"
            value="<?= $e($senderBcc ?? '') ?>"
            placeholder="<?= $te('domain.sender_bcc_placeholder') ?>" />
        </div>
        <div>
          <label for="recipientBcc" class="form-label"><?= $te('domain.recipient_bcc') ?></label>
          <input id="recipientBcc" type="email" name="recipientBcc" data-account-picker="single" class="form-control"
            value="<?= $e($recipientBcc ?? '') ?>"
            placeholder="<?= $te('domain.recipient_bcc_placeholder') ?>" />
        </div>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('domain.save_bcc') ?></button>
      </div>
    </form>

    <?php elseif ($mode === 'relay'): ?>
    <form method="post" class="card">
      <?= $csrfField ?>
      <div class="card-header"><?= $te('domain.relay_legend') ?></div>
      <div class="card-body">
        <p class="text-body-secondary"><?= $te('domain.relay_desc') ?></p>
        <label for="relayhost" class="form-label"><?= $te('domain.relay_host') ?></label>
        <input id="relayhost" type="text" name="relayhost" class="form-control"
          value="<?= $e($domainRelayhost ?? '') ?>"
          placeholder="[smtp.relay.com]:587" />
        <div class="form-text"><?= $t('domain.relay_format') ?></div>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('domain.save_relay') ?></button>
      </div>
    </form>

    <?php elseif ($mode === 'admins'): ?>
    <div class="card">
      <div class="card-header"><?= $te('domain.tab_admins') ?></div>
      <div class="card-body">
        <p class="text-body-secondary"><?= $te($isGlobalAdmin ? 'domain.admins_desc' : 'domain.admins_desc_domain_admin') ?></p>
        <form method="post" class="d-flex flex-wrap gap-2">
          <?= $csrfField ?>
          <input type="hidden" name="action" value="add" />
          <input type="email" name="newAdmin" data-account-picker="single" data-types="user"<?php if (!$isGlobalAdmin): ?> data-domain="<?= $e($domain->domainName) ?>"<?php endif; ?> class="form-control flex-grow-1 w-auto" placeholder="user@<?= $e($domain->domainName) ?>" required aria-label="<?= $te('domain.admin_address') ?>" />
          <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i><?= $te('domain.add_admin') ?></button>
        </form>
      </div>
      <?php if ($domainAdmins === []): ?>
      <div class="card-body pt-0 text-body-secondary"><?= $te('domain.no_admins') ?></div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr>
              <th><?= $te('common.email') ?></th>
              <th class="text-end"><?= $te('common.actions') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($domainAdmins as $admin): ?>
            <tr>
              <td><?= $e($admin) ?></td>
              <td>
                <form method="post" class="table-actions">
                  <?= $csrfField ?>
                  <input type="hidden" name="action" value="remove" />
                  <input type="hidden" name="admin" value="<?= $e($admin) ?>" />
                  <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="<?= $te('domain.remove_admin_confirm', ['address' => $admin]) ?>"><i class="bi bi-x-lg me-1"></i><?= $te('common.remove') ?></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
