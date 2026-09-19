<?php
$pageTitle = $t('user.view_title');
$userPath = '/' . $e($domain) . '/users/' . $e($user->uid);
$fieldClass = fn (string $field): string => 'form-control' . (!empty($validationErrors[$field]) ? ' is-invalid' : '');
// A field that the directory replicates can change only in the directory.
$locked = fn (string $field): string => in_array($field, $lockedFields, true) ? ' readonly' : '';
$tabs = [
    'general' => $t('user.tab_general'),
    'password' => $t('common.password'),
    'services' => $t('user.tab_services'),
    'forwarding' => $t('user.tab_forwarding'),
    'aliases' => $t('user.tab_aliases'),
    'bcc' => $t('domain.tab_bcc'),
    'relay' => $t('domain.tab_relay'),
];
// A domain admin sees only the pages that the global admin left open.
$tabs = array_intersect_key($tabs, array_flip($openPages));
$services = \App\Models\User::SERVICE_LABELS;
?>
<div class="page-header">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/domains"><?= $e($domain) ?></a></li>
        <li class="breadcrumb-item"><a href="/<?= $e($domain) ?>/users"><?= $te('domain.users') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $e($user->uid) ?></li>
      </ol>
    </nav>
    <h1><?= $e($user->uid) ?><span class="text-body-secondary fw-normal">@<?= $e($domain) ?></span>
      <?php if ($managedBy !== null): ?>
      <span class="badge badge-status fs-6 align-middle <?= $tone('managed', 'directory') ?>" title="<?= $te('resource.managed_help') ?>"><i class="bi bi-diagram-3 me-1"></i><?= $te('resource.managed_by') ?></span>
      <?php endif; ?>
    </h1>
  </div>
</div>

<ul class="nav nav-tabs">
  <?php foreach ($tabs as $key => $label): ?>
  <li class="nav-item"><a class="nav-link<?= $editMode === $key ? ' active' : '' ?>" href="<?= $userPath ?>/<?= $key ?>"<?= $editMode === $key ? ' aria-current="page"' : '' ?>><?= $e($label) ?></a></li>
  <?php endforeach; ?>
  <?php if (!empty($features['iredapd']) && !empty($session['isGlobalAdmin'])): ?>
  <li class="nav-item"><a class="nav-link" href="/iredapd/throttle/<?= $e($user->uid . '@' . $domain) ?>"><?= $te('throttle.view_title') ?></a></li>
  <li class="nav-item"><a class="nav-link" href="/iredapd/greylist/<?= $e($user->uid . '@' . $domain) ?>"><?= $te('greylist.view_title') ?></a></li>
  <?php endif; ?>
</ul>

<?php if ($managedBy !== null && $editMode === 'general'): ?>
<div class="alert alert-info"><?= $te('resource.managed_help') ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
<div class="alert alert-success"><?= $e($success) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-xl-8">
    <?php if ($editMode === 'general' && !empty($session['isGlobalAdmin']) && $managedBy === null): ?>
    <details class="card mb-4">
      <summary class="card-header"><?= $te('user.rename_email') ?></summary>
      <div class="card-body">
        <form method="post" action="<?= $userPath ?>/rename" data-confirm="<?= $te('user.rename_confirm') ?>" class="d-flex flex-wrap gap-2">
          <?= $csrfField ?>
          <div class="input-group flex-grow-1 w-auto">
            <input type="text" name="newUid" class="form-control" placeholder="<?= $te('user.new_username') ?>" required />
            <span class="input-group-text">@<?= $e($domain) ?></span>
          </div>
          <button type="submit" class="btn btn-outline-secondary"><?= $te('user.rename') ?></button>
        </form>
      </div>
    </details>
    <?php endif; ?>

    <?php if (in_array($editMode, ['general', 'password', 'services', 'forwarding'], true)): ?>
    <form method="post" class="card">
      <?= $csrfField ?>
      <div class="card-body">
        <?php if ($editMode === 'general'): ?>
        <input type="hidden" value="<?= $e($user->uid) ?>" name="uid" />

        <div class="form-check form-switch mb-3">
          <input id="accountStatus" name="accountStatus" type="checkbox" class="form-check-input" <?php if ($user->accountStatus): ?>checked<?php endif; ?><?= $locked('accountStatus') !== '' ? ' disabled' : '' ?> />
          <label class="form-check-label" for="accountStatus"><?= $te('user.record_active') ?></label>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label for="mailQuota" class="form-label"><?= $te('user.quota_mb_label') ?></label>
            <input id="mailQuota" name="mailQuota" type="number" class="form-control" value="<?= $e($user->mailQuota) ?>" required />
          </div>
          <div class="col-md-6">
            <label for="cn" class="form-label"><?= $te('user.full_name') ?></label>
            <input id="cn" name="cn" type="text" class="form-control" value="<?= $e($user->cn) ?>"<?= $locked('cn') ?> />
          </div>
          <div class="col-md-6">
            <label for="givenName" class="form-label"><?= $te('user.first_name') ?></label>
            <input id="givenName" name="givenName" type="text" class="form-control" value="<?= $e($user->givenName) ?>"<?= $locked('givenName') ?> />
          </div>
          <div class="col-md-6">
            <label for="sn" class="form-label"><?= $te('user.last_name') ?></label>
            <input id="sn" name="sn" type="text" class="form-control" value="<?= $e($user->sn) ?>"<?= $locked('sn') ?> />
          </div>
          <div class="col-md-6">
            <label for="employeeNumber" class="form-label"><?= $te('user.employee_number') ?></label>
            <input id="employeeNumber" name="employeeNumber" type="text" class="form-control" value="<?= $e($user->employeeNumber) ?>"<?= $locked('employeeNumber') ?> />
          </div>
          <div class="col-md-6">
            <label for="title" class="form-label"><?= $te('user.position') ?></label>
            <input id="title" name="title" type="text" class="form-control" value="<?= $e($user->title) ?>"<?= $locked('title') ?> />
          </div>
          <div class="col-md-6">
            <label for="mobile" class="form-label"><?= $te('user.mobile_phone') ?></label>
            <input id="mobile" name="mobile" type="text" class="form-control" value="<?= $e($user->mobile) ?>"<?= $locked('mobile') ?> />
          </div>
          <div class="col-md-6">
            <label for="telephoneNumber" class="form-label"><?= $te('user.work_phone') ?></label>
            <input id="telephoneNumber" name="telephoneNumber" type="text" class="form-control" value="<?= $e($user->telephoneNumber) ?>"<?= $locked('telephoneNumber') ?> />
          </div>
          <div class="col-md-6">
            <label for="language" class="form-label"><?= $te('user.language') ?></label>
            <?php // A stored code that the panel has no translation for stays selectable, so a save keeps it. ?>
            <?php $languages = ['' => $t('user.language_default')] + $availableLocales + [(string) $user->language => (string) $user->language]; ?>
            <select id="language" name="language" class="form-select">
              <?php foreach ($languages as $code => $name): ?>
              <option value="<?= $e($code) ?>"<?= (string) $code === (string) $user->language ? ' selected' : '' ?>><?= $e($name) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <?php if (!empty($session['isGlobalAdmin'])): ?>
        <div class="form-check form-switch mt-3">
          <input id="domainGlobalAdmin" name="domainGlobalAdmin" type="checkbox" class="form-check-input" <?php if ($user->domainGlobalAdmin): ?>checked<?php endif; ?> />
          <label class="form-check-label" for="domainGlobalAdmin"><?= $te('admin.global_administrator') ?></label>
        </div>
        <?php endif; ?>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('common.save') ?></button>
      </div>

        <?php elseif ($editMode === 'password'): ?>
        <?php if (!empty($requireOldPassword)): ?>
        <div class="mb-3">
          <label for="old_password" class="form-label"><?= $te('user.current_password') ?></label>
          <input name="old_password" type="password" id="old_password" required class="<?= $fieldClass('old_password') ?>" />
          <?php if (!empty($validationErrors['old_password'])): ?>
          <div class="invalid-feedback"><?= $e($validationErrors['old_password']) ?></div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
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
        <button type="button" class="btn btn-sm btn-outline-secondary" data-generate-password><i class="bi bi-magic me-1"></i><?= $te('user.generate_password') ?></button>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('common.save') ?></button>
      </div>

        <?php elseif ($editMode === 'services'): ?>
        <h2 class="h6 mb-3"><?= $te('user.mail_services') ?></h2>
        <div class="row g-2">
          <?php foreach ($services as $field => $label): ?>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input type="checkbox" class="form-check-input" id="<?= $field ?>" name="<?= $field ?>" <?php if ($user->$field): ?>checked<?php endif; ?> />
              <label class="form-check-label" for="<?= $field ?>"><?= $e($label) ?></label>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('user.save_services') ?></button>
      </div>

        <?php elseif ($editMode === 'forwarding'): ?>
        <h2 class="h6 mb-3"><?= $te('user.email_forwarding') ?></h2>
        <div class="mb-3">
          <label for="forwardingAddresses" class="form-label"><?= $te('user.forwarding_addresses') ?></label>
          <textarea id="forwardingAddresses" name="forwardingAddresses" data-account-picker="multi" rows="5" class="form-control" placeholder="user@example.com"><?= $e(implode("\n", $forwardings ?? [])) ?></textarea>
        </div>
        <div class="form-check form-switch">
          <input type="checkbox" class="form-check-input" id="keepCopy" name="keepCopy" <?php if ($keepCopy ?? true): ?>checked<?php endif; ?> />
          <label class="form-check-label" for="keepCopy"><?= $te('user.keep_copy') ?></label>
        </div>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('user.save_forwarding') ?></button>
      </div>
        <?php endif; ?>
    </form>
    <?php endif; ?>

    <?php if ($editMode === 'aliases'): ?>
    <div class="card">
      <div class="card-header"><?= $te('user.per_user_aliases') ?></div>
      <div class="card-body">
        <p class="text-body-secondary"><?= $te('user.aliases_desc') ?></p>
        <form method="post" class="d-flex flex-wrap gap-2">
          <?= $csrfField ?>
          <input type="hidden" name="action" value="add" />
          <input type="email" name="newAlias" class="form-control flex-grow-1 w-auto" placeholder="alias@example.com" required />
          <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i><?= $te('user.add_alias') ?></button>
        </form>
      </div>
      <?php if (!empty($userAliases)): ?>
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr>
              <th><?= $te('user.alias_address') ?></th>
              <th class="text-end"><?= $te('common.actions') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($userAliases as $aliasAddr): ?>
            <tr>
              <td><?= $e($aliasAddr) ?></td>
              <td>
                <form method="post" class="table-actions">
                  <?= $csrfField ?>
                  <input type="hidden" name="action" value="remove" />
                  <input type="hidden" name="aliasAddress" value="<?= $e($aliasAddr) ?>" />
                  <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="<?= $e($t('user.alias_remove_confirm', ['alias' => $aliasAddr])) ?>"><i class="bi bi-x-lg me-1"></i><?= $te('wblist.remove') ?></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="card-body pt-0 text-body-secondary"><?= $te('user.no_aliases') ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($editMode === 'bcc'): ?>
    <form method="post" class="card">
      <?= $csrfField ?>
      <div class="card-header"><?= $te('domain.bcc_settings') ?></div>
      <div class="card-body">
        <p class="text-body-secondary"><?= $te('user.bcc_desc') ?></p>
        <div class="mb-3">
          <label for="senderBcc" class="form-label"><?= $te('domain.sender_bcc') ?></label>
          <input id="senderBcc" type="email" name="senderBcc" data-account-picker="single" class="form-control"
            value="<?= $e($userSenderBcc ?? '') ?>"
            placeholder="<?= $te('domain.sender_bcc_placeholder') ?>" />
        </div>
        <div>
          <label for="recipientBcc" class="form-label"><?= $te('domain.recipient_bcc') ?></label>
          <input id="recipientBcc" type="email" name="recipientBcc" data-account-picker="single" class="form-control"
            value="<?= $e($userRecipientBcc ?? '') ?>"
            placeholder="<?= $te('domain.recipient_bcc_placeholder') ?>" />
        </div>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('domain.save_bcc') ?></button>
      </div>
    </form>
    <?php endif; ?>

    <?php if ($editMode === 'relay'): ?>
    <form method="post" class="card">
      <?= $csrfField ?>
      <div class="card-header"><?= $te('domain.relay_legend') ?></div>
      <div class="card-body">
        <p class="text-body-secondary"><?= $te('user.relay_desc') ?></p>
        <label for="relayhost" class="form-label"><?= $te('domain.relay_host') ?></label>
        <input id="relayhost" type="text" name="relayhost" class="form-control"
          value="<?= $e($userRelayhost ?? '') ?>"
          placeholder="[smtp.relay.com]:587" />
        <div class="form-text"><?= $t('domain.relay_format') ?></div>
        <?php if (!empty($session['isGlobalAdmin'])): ?>
        <label for="transport" class="form-label mt-3"><?= $te('user.transport') ?></label>
        <input id="transport" type="text" name="transport" class="form-control font-monospace"
          value="<?= $e($userTransport ?? '') ?>"
          placeholder="dovecot" />
        <div class="form-text"><?= $te('user.transport_hint') ?></div>
        <?php endif; ?>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('domain.save_relay') ?></button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>
