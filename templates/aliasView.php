<?php $pageTitle = $t('alias.view_title', ['address' => $alias->address ?? '']); ?>
<div class="container">
  <div class="row">
    <div class="col-8">
      <h1><?= $te('alias.view_title', ['address' => $alias->address]) ?></h1>

      <?php if (!empty($success)): ?>
      <div class="card bg-success text-white"><?= $e($success) ?></div>
      <?php endif; ?>
      <?php if (!empty($error)): ?>
      <div class="card bg-error text-white"><?= $e($error) ?></div>
      <?php endif; ?>

      <nav class="tabs">
        <a class="active" href="#settings"><?= $te('common.settings') ?></a>
        <a href="#members"><?= $te('alias.members') ?> (<?= count($members) ?>)</a>
        <a href="#moderators"><?= $te('alias.moderators') ?> (<?= count($moderators) ?>)</a>
      </nav>

      <!-- Settings -->
      <form method="post" action="/aliases/<?= $e($alias->address) ?>">
        <?= $csrfField ?>
        <input type="hidden" name="action" value="updateSettings" />

        <fieldset>
          <legend><?= $te('alias.alias_settings') ?></legend>

          <label for="name"><?= $te('alias.display_name') ?></label>
          <input type="text" id="name" name="name" value="<?= $e($alias->name) ?>" />

          <label for="accessPolicy"><?= $te('alias.access_policy') ?></label>
          <select id="accessPolicy" name="accessPolicy">
            <option value="public" <?= $alias->accessPolicy === 'public' ? 'selected' : '' ?>><?= $te('alias.policy_public') ?></option>
            <option value="domain" <?= $alias->accessPolicy === 'domain' ? 'selected' : '' ?>><?= $te('alias.policy_domain') ?></option>
            <option value="membersOnly" <?= $alias->accessPolicy === 'membersOnly' ? 'selected' : '' ?>><?= $te('alias.policy_members') ?></option>
            <option value="moderatorsOnly" <?= $alias->accessPolicy === 'moderatorsOnly' ? 'selected' : '' ?>><?= $te('alias.policy_moderators') ?></option>
          </select>

          <label>
            <input type="checkbox" name="active" <?= $alias->active ? 'checked' : '' ?> />
            <?= $te('common.active') ?>
          </label>

          <label for="members"><?= $te('alias.members_oneline') ?></label>
          <textarea id="members" name="members" rows="8"><?= $e(implode("\n", $members)) ?></textarea>
        </fieldset>

        <button type="submit" class="button primary"><?= $te('mlist.save_settings') ?></button>
      </form>

      <hr />

      <!-- Quick Add Member -->
      <form method="post" action="/aliases/<?= $e($alias->address) ?>">
        <?= $csrfField ?>
        <input type="hidden" name="action" value="addMember" />

        <fieldset>
          <legend><?= $te('alias.quick_add_member') ?></legend>
          <div class="row">
            <div class="col-8">
              <input type="email" name="newMember" placeholder="user@example.com" required />
            </div>
            <div class="col-4">
              <button type="submit" class="button primary outline"><?= $te('alias.add_member') ?></button>
            </div>
          </div>
        </fieldset>
      </form>

      <!-- Current Members List -->
      <?php if (!empty($members)): ?>
      <h3><?= $te('alias.current_members') ?></h3>
      <table class="striped">
        <thead>
          <tr>
            <th><?= $te('common.email') ?></th>
            <th><?= $te('common.actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($members as $member): ?>
          <tr>
            <td><?= $e($member) ?></td>
            <td>
              <form method="post" action="/aliases/<?= $e($alias->address) ?>" style="display:inline">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="removeMember" />
                <input type="hidden" name="member" value="<?= $e($member) ?>" />
                <button type="submit" class="button error outline" data-confirm="<?= $te('alias.remove_confirm', ['member' => $member]) ?>"><?= $te('alias.remove') ?></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>

      <hr />

      <!-- Moderators -->
      <form method="post" action="/aliases/<?= $e($alias->address) ?>">
        <?= $csrfField ?>
        <input type="hidden" name="action" value="updateModerators" />

        <fieldset>
          <legend><?= $te('alias.moderators') ?></legend>
          <label for="moderators"><?= $te('alias.moderators_hint') ?></label>
          <textarea id="moderators" name="moderators" rows="4"><?= $e(implode("\n", $moderators)) ?></textarea>
          <p class="text-light"><?= $te('alias.moderators_desc') ?></p>
        </fieldset>

        <button type="submit" class="button primary outline"><?= $te('alias.save_moderators') ?></button>
      </form>

      <hr />

      <!-- Delete -->
      <?php if (!empty($session['isGlobalAdmin'])): ?>
      <form method="post" action="/aliases/<?= $e($alias->address) ?>/delete" data-confirm="<?= $te('alias.delete_confirm_perm', ['address' => $alias->address]) ?>">
        <?= $csrfField ?>
        <button type="submit" class="button error"><?= $te('alias.delete_button') ?></button>
      </form>
      <?php endif; ?>

      <p><a href="/aliases">&larr; <?= $te('alias.back') ?></a></p>
    </div>
  </div>
</div>
