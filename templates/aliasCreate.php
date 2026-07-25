<?php $pageTitle = $t('alias.create_title'); ?>
<div class="container">
  <div class="row">
    <div class="col-8">
      <h1><?= $te('alias.create_title') ?></h1>

      <?php if (!empty($error)): ?>
      <div class="card bg-error text-white"><?= $e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="/aliases/create">
        <?= $csrfField ?>

        <fieldset>
          <legend><?= $te('alias.alias_address') ?></legend>

          <div class="row">
            <div class="col-6">
              <label for="localPart"><?= $te('alias.local_part') ?></label>
              <input type="text" id="localPart" name="localPart" required value="<?= $e($_POST['localPart'] ?? '') ?>" placeholder="alias-name" />
            </div>
            <div class="col-6">
              <label for="domain"><?= $te('common.domain') ?></label>
              <select id="domain" name="domain" required>
                <?php foreach ($domains as $d): ?>
                <option value="<?= $e($d['domain'] ?? $d['name'] ?? '') ?>"
                  <?= (($_POST['domain'] ?? '') === ($d['domain'] ?? $d['name'] ?? '')) ? 'selected' : '' ?>>
                  @<?= $e($d['domain'] ?? $d['name'] ?? '') ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <label for="name"><?= $te('alias.display_name') ?></label>
          <input type="text" id="name" name="name" value="<?= $e($_POST['name'] ?? '') ?>" placeholder="<?= $te('alias.display_name_placeholder') ?>" />

          <label for="accessPolicy"><?= $te('alias.access_policy') ?></label>
          <select id="accessPolicy" name="accessPolicy">
            <option value="public" <?= (($_POST['accessPolicy'] ?? 'public') === 'public') ? 'selected' : '' ?>><?= $te('alias.policy_public') ?></option>
            <option value="domain" <?= (($_POST['accessPolicy'] ?? '') === 'domain') ? 'selected' : '' ?>><?= $te('alias.policy_domain') ?></option>
            <option value="membersOnly" <?= (($_POST['accessPolicy'] ?? '') === 'membersOnly') ? 'selected' : '' ?>><?= $te('alias.policy_members') ?></option>
            <option value="moderatorsOnly" <?= (($_POST['accessPolicy'] ?? '') === 'moderatorsOnly') ? 'selected' : '' ?>><?= $te('alias.policy_moderators') ?></option>
          </select>
        </fieldset>

        <fieldset>
          <legend><?= $te('alias.members') ?></legend>
          <label for="members"><?= $te('alias.members_hint') ?></label>
          <textarea id="members" name="members" rows="6" placeholder="user1@example.com&#10;user2@example.com"><?= $e($_POST['members'] ?? '') ?></textarea>
        </fieldset>

        <button type="submit" class="button primary"><?= $te('alias.create_button') ?></button>
        <a href="/aliases" class="button outline"><?= $te('common.cancel') ?></a>
      </form>
    </div>
  </div>
</div>
