<?php $pageTitle = $e($admin->username); ?>
<div class="container">
  <div class="row">
    <div class="col-8">
      <h1><?= $e($admin->username) ?></h1>

      <div class="row breadcrumbs">
        <div class="col">
          <a href="/admins"><?= $te('admin.list_title') ?></a> /
          <span class="text-light"><?= $e($admin->username) ?></span>
        </div>
      </div>

      <?php if (!empty($error)): ?>
      <p class="text-error"><?= $e($error) ?></p>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
      <p class="text-success"><?= $e($success) ?></p>
      <?php endif; ?>

      <nav class="tabs">
        <a <?php if ($editMode === 'general'): ?>class="active"<?php endif; ?>
           href="/admins/<?= $e($admin->username) ?>/general">
          <?= $te('admin.tab_general') ?>
        </a>
        <a <?php if ($editMode === 'password'): ?>class="active"<?php endif; ?>
           href="/admins/<?= $e($admin->username) ?>/password">
          <?= $te('common.password') ?>
        </a>
        <a <?php if ($editMode === 'domains'): ?>class="active"<?php endif; ?>
           href="/admins/<?= $e($admin->username) ?>/domains">
          <?= $te('admin.tab_domains') ?>
        </a>
        <a <?php if ($editMode === 'limits'): ?>class="active"<?php endif; ?>
           href="/admins/<?= $e($admin->username) ?>/limits">
          <?= $te('admin.tab_limits') ?>
        </a>
      </nav>

      <?php if ($editMode === 'general'): ?>
      <form method="post">
        <?= $csrfField ?>

        <p>
          <label for="name"><?= $te('admin.display_name') ?></label>
          <input id="name" type="text" name="name"
            value="<?= $e($admin->name) ?>"
          />
        </p>

        <p>
          <label>
            <input type="checkbox" name="isGlobalAdmin" <?php if ($admin->isGlobalAdmin): ?>checked<?php endif; ?> />
            <?= $te('admin.global_administrator') ?>
          </label>
        </p>

        <p>
          <label>
            <input type="checkbox" name="active" <?php if ($admin->active): ?>checked<?php endif; ?> />
            <?= $te('common.active') ?>
          </label>
        </p>

        <p class="text-light">
          <?= $te('common.type') ?>: <?= $e($admin->isMailboxAdmin ? $t('admin.type_mailbox') : $t('admin.type_standalone')) ?> |
          <?= $te('admin.created') ?>: <?= $e($admin->created ?? $t('common.na')) ?>
        </p>

        <button type="submit" class="button primary"><?= $te('common.save_changes') ?></button>
      </form>

      <?php elseif ($editMode === 'password'): ?>
      <form method="post">
        <?= $csrfField ?>

        <p>
          <label for="password"><?= $te('user.new_password') ?></label>
          <input id="password" type="password" name="password" required
            <?php if (!empty($validationErrors['password'])): ?>class="error"<?php endif; ?>
          />
          <?php if (!empty($validationErrors['password'])): ?>
          <span class="text-error"><?= $e($validationErrors['password']) ?></span>
          <?php endif; ?>
        </p>

        <p>
          <label for="password_repeat"><?= $te('admin.repeat_password') ?></label>
          <input id="password_repeat" type="password" name="password_repeat" required
            <?php if (!empty($validationErrors['password_repeat'])): ?>class="error"<?php endif; ?>
          />
          <?php if (!empty($validationErrors['password_repeat'])): ?>
          <span class="text-error"><?= $e($validationErrors['password_repeat']) ?></span>
          <?php endif; ?>
        </p>
        <p>
          <button type="button" class="button outline" onclick="generatePassword()"><?= $te('user.generate_password') ?></button>
        </p>

        <button type="submit" class="button primary"><?= $te('user.change_password') ?></button>
      </form>

      <?php elseif ($editMode === 'domains'): ?>
      <h3><?= $te('admin.tab_domains') ?></h3>

      <?php if (!empty($managedDomains)): ?>
      <table class="striped">
        <thead>
          <tr>
            <th><?= $te('common.domain') ?></th>
            <th><?= $te('common.action') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($managedDomains as $managedDomain): ?>
          <tr>
            <td><?= $e($managedDomain) ?></td>
            <td>
              <form method="post" style="display:inline">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="revoke" />
                <input type="hidden" name="domain" value="<?= $e($managedDomain) ?>" />
                <button type="submit" class="button error outline"><?= $te('admin.revoke') ?></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <p class="text-light"><?= $te('admin.no_domains_assigned') ?></p>
      <?php endif; ?>

      <h3><?= $te('admin.assign_domain') ?></h3>
      <form method="post">
        <?= $csrfField ?>
        <input type="hidden" name="action" value="assign" />
        <div class="row">
          <div class="col-8">
            <select name="domain">
              <?php foreach ($allDomainNames as $domainName): ?>
                <?php if (!in_array($domainName, $managedDomains, true)): ?>
                <option value="<?= $e($domainName) ?>"><?= $e($domainName) ?></option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-4">
            <button type="submit" class="button primary outline"><?= $te('admin.assign') ?></button>
          </div>
        </div>
      </form>
      <?php endif; ?>

      <?php if ($editMode === 'limits'): ?>
      <h3><?= $te('admin.resource_limits') ?></h3>
      <p class="text-light"><?= $te('admin.resource_limits_hint') ?></p>

      <form method="post">
        <?= $csrfField ?>

        <div class="row">
          <div class="col-4">
            <label for="createMaxDomains"><?= $te('admin.max_domains') ?></label>
            <input type="number" id="createMaxDomains" name="createMaxDomains" min="-1"
              value="<?= $e($admin->createMaxDomains) ?>" />
          </div>
          <div class="col-4">
            <label for="createMaxUsers"><?= $te('admin.max_users') ?></label>
            <input type="number" id="createMaxUsers" name="createMaxUsers" min="-1"
              value="<?= $e($admin->createMaxUsers) ?>" />
          </div>
          <div class="col-4">
            <label for="createMaxAliases"><?= $te('admin.max_aliases') ?></label>
            <input type="number" id="createMaxAliases" name="createMaxAliases" min="-1"
              value="<?= $e($admin->createMaxAliases) ?>" />
          </div>
        </div>

        <div class="row">
          <div class="col-4">
            <label for="createMaxLists"><?= $te('admin.max_lists') ?></label>
            <input type="number" id="createMaxLists" name="createMaxLists" min="-1"
              value="<?= $e($admin->createMaxLists) ?>" />
          </div>
          <div class="col-4">
            <label for="createMaxQuota"><?= $te('admin.max_quota') ?></label>
            <input type="number" id="createMaxQuota" name="createMaxQuota" min="-1"
              value="<?= $e($admin->createMaxQuota) ?>" />
          </div>
          <div class="col-4">
            <label>
              <input type="checkbox" name="createNewDomains" <?= $admin->createNewDomains ? 'checked' : '' ?> />
              <?= $te('admin.allow_domain_creation') ?>
            </label>
          </div>
        </div>

        <button type="submit" class="button primary"><?= $te('admin.save_limits') ?></button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
