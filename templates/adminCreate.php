<?php $pageTitle = $t('admin.create'); ?>
<div class="container">
  <div class="row">
    <div class="col-8">
      <h1><?= $te('admin.create') ?></h1>

      <div class="row breadcrumbs">
        <div class="col">
          <a href="/admins"><?= $te('admin.list_title') ?></a> /
          <span class="text-light"><?= $te('common.create') ?></span>
        </div>
      </div>

      <?php if (!empty($error)): ?>
      <p class="text-error"><?= $e($error) ?></p>
      <?php endif; ?>

      <form method="post">
        <?= $csrfField ?>

        <p>
          <label for="username"><?= $te('admin.email_address') ?></label>
          <input id="username" type="email" name="username" required placeholder="admin@example.com"
            <?php if (!empty($validationErrors['username'])): ?>class="error"<?php endif; ?>
            value="<?= $e($admin?->username ?? '') ?>"
          />
          <?php if (!empty($validationErrors['username'])): ?>
          <span class="text-error"><?= $e($validationErrors['username']) ?></span>
          <?php endif; ?>
        </p>

        <p>
          <label for="name"><?= $te('admin.display_name') ?></label>
          <input id="name" type="text" name="name"
            value="<?= $e($admin?->name ?? '') ?>"
          />
        </p>

        <p>
          <label for="password"><?= $te('common.password') ?></label>
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

        <p>
          <label>
            <input type="checkbox" name="isGlobalAdmin" <?php if ($admin === null || ($admin->isGlobalAdmin ?? false)): ?>checked<?php endif; ?> />
            <?= $te('admin.global_administrator') ?>
          </label>
        </p>

        <p>
          <label>
            <input type="checkbox" name="active" <?php if ($admin === null || ($admin->active ?? true)): ?>checked<?php endif; ?> />
            <?= $te('common.active') ?>
          </label>
        </p>

        <button type="submit" class="button primary"><?= $te('admin.create') ?></button>
        <a href="/admins" class="button outline"><?= $te('common.cancel') ?></a>
      </form>
    </div>
  </div>
</div>
