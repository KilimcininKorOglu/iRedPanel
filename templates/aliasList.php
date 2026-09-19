<?php $pageTitle = $t('alias.list_title'); ?>
<div class="page-header">
  <h1><?= $te('alias.list_title') ?></h1>
  <div class="page-actions">
    <form method="get" action="/aliases">
      <select name="domain" class="form-select" data-autosubmit aria-label="<?= $te('common.domain') ?>">
        <option value=""><?= $te('common.all_domains') ?></option>
        <?php foreach ($domains as $d): ?>
        <option value="<?= $e($d['domainName']) ?>"<?= ($filterDomain === $d['domainName']) ? ' selected' : '' ?>><?= $e($d['domainName']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php if (!empty($session['isGlobalAdmin'])): ?>
    <a href="/aliases/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i><?= $te('alias.create') ?></a>
    <?php endif; ?>
  </div>
</div>

<form method="post" action="/aliases/bulk">
  <?= $csrfField ?>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr>
            <th><input type="checkbox" class="form-check-input" data-select-all="selected[]" /></th>
            <th><?= $te('common.address') ?></th>
            <th><?= $te('common.name') ?></th>
            <th><?= $te('common.domain') ?></th>
            <th><?= $te('alias.access_policy') ?></th>
            <th><?= $te('common.status') ?></th>
            <th><?= $te('common.created') ?></th>
            <th class="text-end"><?= $te('common.actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($aliases as $alias): ?>
          <tr>
            <td><input type="checkbox" class="form-check-input" name="selected[]" value="<?= $e($alias->address) ?>" /></td>
            <td><a href="/aliases/<?= $e($alias->address) ?>" class="fw-medium"><?= $e($alias->address) ?></a></td>
            <td><?= $e($alias->name) ?></td>
            <td><a href="/<?= $e($alias->domain) ?>/users"><?= $e($alias->domain) ?></a></td>
            <td><span class="badge text-bg-secondary badge-status"><?= $e($alias->accessPolicy) ?></span></td>
            <td><?= $localize($alias->active ? 'active' : 'disabled') ?></td>
            <td class="text-body-secondary"><?= $e($alias->created ?? '') ?></td>
            <td>
              <div class="table-actions">
                <a href="/aliases/<?= $e($alias->address) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i><?= $te('common.edit') ?></a>
                <?php /* A form cannot nest inside the bulk form: the button posts the bulk form to the delete route. */ ?>
                <button type="submit" formaction="/aliases/<?= $e($alias->address) ?>/delete" formnovalidate class="btn btn-sm btn-outline-danger" data-confirm="<?= $te('alias.delete_confirm', ['address' => $alias->address]) ?>"><i class="bi bi-trash3 me-1"></i><?= $te('common.delete') ?></button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($aliases)): ?>
          <tr><td colspan="8" class="text-center text-body-secondary py-4"><?= $te('alias.empty') ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (!empty($aliases) && !empty($session['isGlobalAdmin'])): ?>
  <div class="d-flex flex-wrap gap-2 align-items-center">
    <select name="action" class="form-select form-select-sm w-auto">
      <option value=""><?= $te('common.bulk_action') ?></option>
      <option value="enable"><?= $te('common.enable') ?></option>
      <option value="disable"><?= $te('common.disable') ?></option>
      <option value="delete"><?= $te('common.delete') ?></option>
    </select>
    <button type="submit" class="btn btn-sm btn-outline-secondary" data-bulk-confirm="<?= $e(json_encode(['*' => $t('common.apply_bulk_confirm')])) ?>"><?= $te('common.apply') ?></button>
  </div>
  <?php endif; ?>
</form>

<?php if (isset($paginatedResult)): ?>
  <?php include __DIR__ . '/pagination.php'; ?>
<?php endif; ?>
