<?php $pageTitle = $t('admin.list_title'); ?>
<div class="container">
  <div class="row">
    <div class="col">
      <h1><?= $te('admin.list_title') ?></h1>

      <div class="row">
        <div class="col">
          <a href="/admins/create" class="button primary outline"><?= $te('admin.create') ?></a>
        </div>
      </div>

      <form method="post" action="/admins/bulk">
        <?= $csrfField ?>
      <table class="striped">
        <thead>
          <tr>
            <th><input type="checkbox" id="selectAll" onclick="document.querySelectorAll('input[name=\\'selectedAdmins[]\\']').forEach(c=>c.checked=this.checked)" /></th>
            <th><?= $te('common.email') ?></th>
            <th><?= $te('common.name') ?></th>
            <th><?= $te('admin.global_admin') ?></th>
            <th><?= $te('common.type') ?></th>
            <th><?= $te('common.status') ?></th>
            <th><?= $te('common.actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($admins as $admin): ?>
          <tr>
            <td><input type="checkbox" name="selectedAdmins[]" value="<?= $e($admin->username) ?>" /></td>
            <td>
              <a href="/admins/<?= $e($admin->username) ?>/general"><?= $e($admin->username) ?></a>
            </td>
            <td><?= $e($admin->name) ?></td>
            <td><?= $localize($admin->isGlobalAdmin) ?></td>
            <td><?= $e($admin->isMailboxAdmin ? $t('admin.type_mailbox') : $t('admin.type_standalone')) ?></td>
            <td><?= $localize($admin->active) ?></td>
            <td>
              <a href="/admins/<?= $e($admin->username) ?>/general" class="button primary outline"><?= $te('common.edit') ?></a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div style="margin-top: 0.5rem;">
        <select name="action" required>
          <option value=""><?= $te('common.bulk_action') ?></option>
          <option value="enable"><?= $te('common.enable_selected') ?></option>
          <option value="disable"><?= $te('common.disable_selected') ?></option>
          <option value="delete"><?= $te('common.delete_selected') ?></option>
        </select>
        <button type="submit" class="button outline" onclick="return this.form.action.value==='delete' ? confirm(<?= htmlspecialchars(json_encode($t('admin.bulk_delete_confirm')), ENT_QUOTES) ?>) : true"><?= $te('common.apply') ?></button>
      </div>
      </form>
    </div>
  </div>
</div>
