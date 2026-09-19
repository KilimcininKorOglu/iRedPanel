<?php $pageTitle = $t('admin.list_title'); ?>
<div class="page-header">
  <h1><?= $te('admin.list_title') ?></h1>
  <div class="page-actions">
    <a href="/admins/create" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i><?= $te('admin.create') ?></a>
  </div>
</div>

<form method="post" action="/admins/bulk">
  <?= $csrfField ?>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr>
            <th><input type="checkbox" class="form-check-input" id="selectAll" data-select-all="selectedAdmins[]" /></th>
            <th><?= $te('common.email') ?></th>
            <th><?= $te('common.name') ?></th>
            <th><?= $te('admin.global_admin') ?></th>
            <th><?= $te('common.type') ?></th>
            <th><?= $te('common.status') ?></th>
            <th class="text-end"><?= $te('common.actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($admins as $admin): ?>
          <tr>
            <td><input type="checkbox" class="form-check-input" name="selectedAdmins[]" value="<?= $e($admin->username) ?>" /></td>
            <td><a href="/admins/<?= $e($admin->username) ?>/general" class="fw-medium"><?= $e($admin->username) ?></a></td>
            <td><?= $e($admin->name) ?></td>
            <td><?= $localize($admin->isGlobalAdmin) ?></td>
            <td><span class="badge badge-status <?= $tone('admin_type', $admin->isMailboxAdmin ? 'mailbox' : 'standalone') ?>"><?= $e($admin->isMailboxAdmin ? $t('admin.type_mailbox') : $t('admin.type_standalone')) ?></span></td>
            <td><?= $localize($admin->active) ?></td>
            <td>
              <div class="table-actions">
                <a href="/admins/<?= $e($admin->username) ?>/general" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i><?= $te('common.edit') ?></a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="d-flex flex-wrap gap-2 align-items-center">
    <select name="action" class="form-select form-select-sm w-auto" required>
      <option value=""><?= $te('common.bulk_action') ?></option>
      <option value="enable"><?= $te('common.enable_selected') ?></option>
      <option value="disable"><?= $te('common.disable_selected') ?></option>
      <option value="delete"><?= $te('common.delete_selected') ?></option>
    </select>
    <button type="submit" class="btn btn-sm btn-outline-secondary" data-bulk-confirm="<?= $e(json_encode(['delete' => $t('admin.bulk_delete_confirm')])) ?>"><?= $te('common.apply') ?></button>
  </div>
</form>
