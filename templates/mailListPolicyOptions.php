<?php
// The access policy select of a mail list. The including template sets $accessPolicy.
$policies = [
    'public' => 'alias.policy_public',
    'domain' => 'alias.policy_domain',
    'subdomain' => 'maillist.policy_subdomain',
    'membersOnly' => 'alias.policy_members',
    'moderatorsOnly' => 'alias.policy_moderators',
    'allowedOnly' => 'maillist.policy_allowed',
];
?>
<select id="accessPolicy" name="accessPolicy" class="form-select">
  <?php foreach ($policies as $value => $label): ?>
  <option value="<?= $value ?>"<?= ($accessPolicy ?? 'public') === $value ? ' selected' : '' ?>><?= $te($label) ?></option>
  <?php endforeach; ?>
</select>
