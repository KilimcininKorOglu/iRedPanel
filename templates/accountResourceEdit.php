<?php
$pageTitle = $t('resource.edit_title', ['type' => $t("resource.type_{$resource->type}")]);
$action = '/account-resources/' . $e($resource->id);
$tabs = ['connection', 'replication', 'users'];
if ($resource->replicateGroups) {
    $tabs[] = 'groups';
}
$attributeSuggestions = ['userPrincipalName', 'mail', 'displayName', 'cn', 'givenName', 'sn', 'employeeID', 'employeeNumber',
    'title', 'mobile', 'telephoneNumber', 'userAccountControl', 'department', 'description'];
$profileLabels = \App\Models\AccountResourceForm::PROPERTY_LABELS;
// After a rejected or tested POST the form shows the posted values, so the admin can correct them.
$posted ??= null;
$value = static fn(string $name, mixed $stored): string => is_string($posted[$name] ?? null) ? $posted[$name] : (string) $stored;
$checked = static fn(string $name, bool $stored): string => ($posted !== null ? !empty($posted[$name]) : $stored) ? ' checked' : '';
?>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <h1><?= $e($pageTitle) ?></h1>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= $action ?>/log"><i class="bi bi-journal-text me-1"></i><?= $te('resource.log') ?></a>
    <a class="btn btn-outline-secondary" href="/account-resources"><i class="bi bi-arrow-left me-1"></i><?= $te('common.back') ?></a>
  </div>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>

<ul class="nav nav-tabs">
  <?php foreach ($tabs as $tab): ?>
  <li class="nav-item"><a class="nav-link<?= $activeTab === $tab ? ' active' : '' ?>" href="<?= $action ?>?tab=<?= $tab ?>"<?= $activeTab === $tab ? ' aria-current="page"' : '' ?>><?= $te("resource.tab_{$tab}") ?></a></li>
  <?php endforeach; ?>
</ul>

<datalist id="attribute-suggestions">
  <?php foreach ($attributeSuggestions as $attribute): ?>
  <option value="<?= $e($attribute) ?>"></option>
  <?php endforeach; ?>
</datalist>

<div class="row">
  <div class="col-xxl-9">
    <form method="post" action="<?= $action ?>" class="card">
      <?= $csrfField ?>
      <input type="hidden" name="tab" value="<?= $e($activeTab) ?>">
      <div class="card-body">
        <?php if ($activeTab === 'connection'): ?>
        <div class="row g-3">
          <div class="col-md-6">
            <label for="domain" class="form-label"><?= $te('resource.target_domain') ?></label>
            <select id="domain" name="domain" class="form-select" required>
              <?php foreach ($domains as $d): ?>
              <option value="<?= $e($d['domainName']) ?>"<?= $value('domain', $resource->domain) === $d['domainName'] ? ' selected' : '' ?>><?= $e($d['domainName']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text"><?= $te('resource.target_domain_help') ?></div>
          </div>
          <div class="col-md-4">
            <label for="host" class="form-label"><?= $te('resource.host') ?></label>
            <input type="text" id="host" name="host" class="form-control" required value="<?= $e($value('host', $resource->host)) ?>" placeholder="dc1.example.com">
          </div>
          <div class="col-md-2">
            <label for="port" class="form-label"><?= $te('resource.port') ?></label>
            <input type="number" id="port" name="port" class="form-control" required min="1" max="65535" value="<?= $e($value('port', $resource->port)) ?>">
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input type="checkbox" class="form-check-input" id="tls" name="tls" value="1"<?= $checked('tls', $resource->tls) ?>>
              <label class="form-check-label" for="tls"><?= $te('resource.tls') ?></label>
            </div>
            <div class="form-text"><?= $te('resource.tls_help') ?></div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input type="checkbox" class="form-check-input" id="tlsVerify" name="tlsVerify" value="1"<?= $checked('tlsVerify', $resource->tlsVerify) ?>>
              <label class="form-check-label" for="tlsVerify"><?= $te('resource.tls_verify') ?></label>
            </div>
            <div class="form-text"><?= $te('resource.tls_verify_help') ?></div>
          </div>
          <div class="col-md-4">
            <label for="timeout" class="form-label"><?= $te('resource.timeout') ?></label>
            <input type="number" id="timeout" name="timeout" class="form-control" required min="1" max="60" value="<?= $e($value('timeout', $resource->timeout)) ?>">
          </div>
          <div class="col-md-8">
            <label for="baseDn" class="form-label"><?= $te('resource.base_dn') ?></label>
            <input type="text" id="baseDn" name="baseDn" class="form-control" required value="<?= $e($value('baseDn', $resource->baseDn)) ?>" placeholder="CN=Users,DC=example,DC=com">
          </div>
          <div class="col-md-6">
            <label for="bindDn" class="form-label"><?= $te('resource.bind_dn') ?></label>
            <input type="text" id="bindDn" name="bindDn" class="form-control" required value="<?= $e($value('bindDn', $resource->bindDn)) ?>" placeholder="CN=svc-mail,CN=Users,DC=example,DC=com" autocomplete="off">
          </div>
          <div class="col-md-6">
            <label for="bindPassword" class="form-label"><?= $te('resource.bind_password') ?></label>
            <input type="password" id="bindPassword" name="bindPassword" class="form-control" autocomplete="new-password"<?= $hasPassword ? '' : ' required' ?> placeholder="<?= $hasPassword ? $te('resource.password_keep') : '' ?>">
          </div>
          <div class="col-12">
            <label for="userFilter" class="form-label"><?= $te('resource.user_filter') ?></label>
            <input type="text" id="userFilter" name="userFilter" class="form-control font-monospace" required value="<?= $e($value('userFilter', $resource->userFilter)) ?>">
          </div>
          <div class="col-12">
            <label for="groupFilter" class="form-label"><?= $te('resource.group_filter') ?></label>
            <input type="text" id="groupFilter" name="groupFilter" class="form-control font-monospace" required value="<?= $e($value('groupFilter', $resource->groupFilter)) ?>">
          </div>
        </div>
        <?php elseif ($activeTab === 'replication'): ?>
        <div class="row g-3">
          <div class="col-md-4">
            <label for="intervalMinutes" class="form-label"><?= $te('resource.interval') ?></label>
            <div class="input-group">
              <input type="number" id="intervalMinutes" name="intervalMinutes" class="form-control" required min="1" max="1440" value="<?= $e($value('intervalMinutes', $resource->intervalMinutes)) ?>">
              <span class="input-group-text"><?= $te('resource.minutes') ?></span>
            </div>
            <div class="form-text"><?= $te('resource.interval_help') ?></div>
          </div>
          <div class="col-12">
            <div class="form-check form-switch">
              <input type="checkbox" class="form-check-input" id="replicateGroups" name="replicateGroups" value="1"<?= $checked('replicateGroups', $resource->replicateGroups) ?>>
              <label class="form-check-label" for="replicateGroups"><?= $te('resource.replicate_groups') ?></label>
            </div>
            <div class="form-text"><?= $te('resource.replicate_groups_help') ?></div>
          </div>
          <div class="col-12">
            <div class="alert alert-info mb-0"><?= $te('resource.removal_note') ?></div>
          </div>
        </div>
        <?php elseif ($activeTab === 'users'): ?>
        <div class="row g-3">
          <div class="col-md-6">
            <label for="userMailAttribute" class="form-label"><?= $te('resource.mail_attribute') ?></label>
            <input type="text" id="userMailAttribute" name="userMailAttribute" class="form-control" list="attribute-suggestions" required value="<?= $e($value('userMailAttribute', $resource->userMailAttribute)) ?>">
            <div class="form-text"><?= $te('resource.mail_attribute_help', ['domain' => $resource->domain ?: 'example.com']) ?></div>
          </div>
        </div>
        <h2 class="h6 mt-4"><?= $te('resource.profile_mapping') ?></h2>
        <p class="small text-body-secondary"><?= $te('resource.profile_mapping_help') ?></p>
        <div class="row g-3">
          <?php foreach ($profileLabels as $property => $labelKey): ?>
          <div class="col-md-6">
            <label for="attr_<?= $e($property) ?>" class="form-label"><?= $te($labelKey) ?></label>
            <input type="text" id="attr_<?= $e($property) ?>" name="attr_<?= $e($property) ?>" class="form-control" list="attribute-suggestions" value="<?= $e($value("attr_{$property}", $resource->userAttributes[$property] ?? '')) ?>">
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="row g-3">
          <div class="col-md-6">
            <label for="groupMailAttribute" class="form-label"><?= $te('resource.mail_attribute') ?></label>
            <input type="text" id="groupMailAttribute" name="groupMailAttribute" class="form-control" list="attribute-suggestions" required value="<?= $e($value('groupMailAttribute', $resource->groupMailAttribute)) ?>">
          </div>
          <div class="col-md-6">
            <label for="groupNameAttribute" class="form-label"><?= $te('user.full_name') ?></label>
            <input type="text" id="groupNameAttribute" name="groupNameAttribute" class="form-control" list="attribute-suggestions" value="<?= $e($value('groupNameAttribute', $resource->groupNameAttribute)) ?>">
          </div>
          <div class="col-md-6">
            <label for="groupAccessPolicy" class="form-label"><?= $te('resource.default_access_policy') ?></label>
            <select id="groupAccessPolicy" name="groupAccessPolicy" class="form-select">
              <?php foreach (['public' => 'alias.policy_public', 'domain' => 'alias.policy_domain', 'membersOnly' => 'alias.policy_members', 'moderatorsOnly' => 'alias.policy_moderators'] as $policy => $labelKey): ?>
              <option value="<?= $policy ?>"<?= $value('groupAccessPolicy', $resource->groupAccessPolicy) === $policy ? ' selected' : '' ?>><?= $te($labelKey) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text"><?= $te('resource.default_access_policy_help') ?></div>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $te('common.save') ?></button>
        <?php if ($activeTab === 'connection'): ?>
        <button type="submit" class="btn btn-outline-secondary" formaction="<?= $action ?>/test"><i class="bi bi-plug me-1"></i><?= $te('resource.test_connection') ?></button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<?php if ($preview !== null): ?>
<div class="alert alert-success"><?= $te('resource.connection_ok') ?></div>
<?php foreach (['users', 'groups'] as $kind): ?>
<h2 class="h5 mt-4"><?= $te("resource.preview_{$kind}") ?></h2>
<div class="card">
  <div class="table-responsive">
    <table class="table table-sm table-striped">
      <thead>
        <tr>
          <th><?= $te('common.email') ?></th>
          <th><?= $te('user.full_name') ?></th>
          <?php if ($kind === 'users'): ?>
          <th><?= $te('user.first_name') ?></th>
          <th><?= $te('user.last_name') ?></th>
          <th><?= $te('user.employee_number') ?></th>
          <th><?= $te('user.position') ?></th>
          <th><?= $te('common.status') ?></th>
          <?php else: ?>
          <th><?= $te('resource.member_count') ?></th>
          <?php endif; ?>
          <th><?= $te('resource.note') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($preview[$kind] as $a): ?>
        <tr>
          <td><?= $a->address !== '' ? $e($a->address) : '<span class="text-body-secondary small text-break">' . $e($a->dn) . '</span>' ?></td>
          <td><?= $e($kind === 'users' ? ($a->profile['cn'] ?? '') : $a->name) ?></td>
          <?php if ($kind === 'users'): ?>
          <td><?= $e($a->profile['givenName'] ?? '') ?></td>
          <td><?= $e($a->profile['sn'] ?? '') ?></td>
          <td><?= $e($a->profile['employeeNumber'] ?? '') ?></td>
          <td><?= $e($a->profile['title'] ?? '') ?></td>
          <td><?= $a->active === null ? '' : ($a->active ? $te('common.active') : $te('common.inactive')) ?></td>
          <?php else: ?>
          <td><?= $e(count($a->memberDns)) ?></td>
          <?php endif; ?>
          <td class="small text-body-secondary"><?= $a->isUsable() ? '' : $te("resource.skip_{$a->skipReason}") ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if ($preview[$kind] === []): ?>
        <tr><td colspan="8" class="text-center text-body-secondary py-3"><?= $te('resource.preview_empty') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
