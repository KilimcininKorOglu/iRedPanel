<?php $pageTitle = $t('domain.list_title'); ?>
<div class="container">
  <div class="row">
    <div class="col">
      <h1><?= $te('domain.list_title') ?></h1>

      <div class="row">
        <div class="col">
          <a href="/domains/create" class="button primary outline"><?= $te('domain.create') ?></a>
        </div>
      </div>

      <div style="margin: 0.5rem 0;">
        <a href="/domains" <?php if (empty($statusFilter)): ?>style="font-weight:bold"<?php endif; ?>><?= $te('common.all') ?></a> |
        <a href="/domains?status=active" <?php if (($statusFilter ?? '') === 'active'): ?>style="font-weight:bold"<?php endif; ?>><?= $te('common.active') ?></a> |
        <a href="/domains?status=disabled" <?php if (($statusFilter ?? '') === 'disabled'): ?>style="font-weight:bold"<?php endif; ?>><?= $te('common.disabled') ?></a>
      </div>

      <form method="post" action="/domains/bulk">
        <?= $csrfField ?>
      <table class="striped">
        <thead>
          <tr>
            <th><input type="checkbox" id="selectAll" onclick="document.querySelectorAll('input[name=\\'selectedDomains[]\\']').forEach(c=>c.checked=this.checked)" /></th>
            <th><?= $te('common.name') ?></th>
            <th><?= $te('common.description') ?></th>
            <th><?= $te('domain.users') ?></th>
            <th><?= $te('common.status') ?></th>
            <th><?= $te('common.actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($domains as $domain): ?>
          <tr>
            <td><input type="checkbox" name="selectedDomains[]" value="<?= $e($domain->domainName) ?>" /></td>
            <td>
              <a href="/<?= $e($domain->domainName) ?>/users"><?= $e($domain->domainName) ?></a>
            </td>
            <td><?= $e($domain->description) ?></td>
            <td><?= $e($domain->currentUserCount) ?></td>
            <td><?= $localize($domain->active ? 'active' : 'disabled') ?></td>
            <td>
              <a href="/domains/<?= $e($domain->domainName) ?>/edit" class="button primary outline"><?= $te('common.edit') ?></a>
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
        <button type="submit" class="button outline" onclick="return this.form.action.value==='delete' ? confirm(<?= htmlspecialchars(json_encode($t('domain.bulk_delete_confirm')), ENT_QUOTES) ?>) : true"><?= $te('common.apply') ?></button>
      </div>
      </form>

      <?php if (isset($paginatedResult)): ?>
        <?php include __DIR__ . '/pagination.php'; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
