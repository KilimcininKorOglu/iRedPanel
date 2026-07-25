<?php $pageTitle = $t('mlist.view_title', ['address' => $ml->address ?? '']); ?>
<div class="container">
  <div class="row">
    <div class="col-8">
      <h1><?= $te('mlist.view_title', ['address' => $ml->address]) ?></h1>

      <?php if (!empty($success)): ?>
      <div class="card bg-success text-white"><?= $e($success) ?></div>
      <?php endif; ?>
      <?php if (!empty($error)): ?>
      <div class="card bg-error text-white"><?= $e($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <?= $csrfField ?>
        <input type="hidden" name="action" value="updateSettings" />

        <fieldset>
          <legend><?= $te('mlist.list_settings') ?></legend>

          <label for="name"><?= $te('alias.display_name') ?></label>
          <input type="text" id="name" name="name" value="<?= $e($ml->name) ?>" />

          <label for="accessPolicy"><?= $te('alias.access_policy') ?></label>
          <select id="accessPolicy" name="accessPolicy">
            <option value="public" <?= $ml->accessPolicy === 'public' ? 'selected' : '' ?>><?= $te('mlist.policy_public_short') ?></option>
            <option value="domain" <?= $ml->accessPolicy === 'domain' ? 'selected' : '' ?>><?= $te('common.domain') ?></option>
            <option value="membersOnly" <?= $ml->accessPolicy === 'membersOnly' ? 'selected' : '' ?>><?= $te('alias.policy_members') ?></option>
            <option value="moderatorsOnly" <?= $ml->accessPolicy === 'moderatorsOnly' ? 'selected' : '' ?>><?= $te('alias.policy_moderators') ?></option>
          </select>

          <div class="row">
            <div class="col-6">
              <label for="maxMsgSize"><?= $te('mlist.max_msg_size') ?></label>
              <input type="number" id="maxMsgSize" name="maxMsgSize" min="0" value="<?= $e($ml->maxMsgSize) ?>" />
            </div>
            <div class="col-6">
              <label for="maxMembers"><?= $te('mlist.max_members') ?></label>
              <input type="number" id="maxMembers" name="maxMembers" min="0" value="<?= $e($ml->maxMembers) ?>" />
            </div>
          </div>

          <label>
            <input type="checkbox" name="active" <?= $ml->active ? 'checked' : '' ?> />
            <?= $te('common.active') ?>
          </label>

          <p class="text-light"><?= $te('mlist.transport') ?>: <?= $e($ml->transport) ?></p>
        </fieldset>

        <button type="submit" class="button primary"><?= $te('mlist.save_settings') ?></button>
      </form>

      <hr />

      <form method="post">
        <?= $csrfField ?>
        <input type="hidden" name="action" value="updateOwners" />

        <fieldset>
          <legend><?= $te('mlist.list_owners') ?></legend>
          <label for="owners"><?= $te('mlist.owners_hint') ?></label>
          <textarea id="owners" name="owners" rows="4"><?= $e(implode("\n", $owners)) ?></textarea>
          <p class="text-light"><?= $te('mlist.owners_desc') ?></p>
        </fieldset>

        <button type="submit" class="button primary outline"><?= $te('mlist.save_owners') ?></button>
      </form>

      <hr />

      <?php if (!empty($session['isGlobalAdmin'])): ?>
      <form method="post" action="/mailing-lists/<?= $e($ml->address) ?>/delete" data-confirm="<?= $te('mlist.delete_confirm', ['address' => $ml->address]) ?>">
        <?= $csrfField ?>
        <button type="submit" class="button error"><?= $te('mlist.delete_button') ?></button>
      </form>
      <?php endif; ?>

      <p><a href="/mailing-lists">&larr; <?= $te('mlist.back') ?></a></p>
    </div>
  </div>
</div>
