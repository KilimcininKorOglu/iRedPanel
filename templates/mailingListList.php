<?php $pageTitle = $t('mlist.list_title'); ?>
<div class="page-header">
  <h1><?= $te('mlist.list_title') ?></h1>
  <div class="page-actions">
    <form method="get" action="/mailing-lists" class="d-flex gap-2">
      <?php include __DIR__ . '/statusSelect.php'; ?>
      <select name="domain" class="form-select" data-autosubmit aria-label="<?= $te('common.domain') ?>">
        <?php if (!empty($session['isGlobalAdmin'])): ?>
        <option value=""><?= $te('common.all_domains') ?></option>
        <?php endif; ?>
        <?php foreach ($domains as $d): ?>
        <option value="<?= $e($d['domainName']) ?>"<?= ($filterDomain === $d['domainName']) ? ' selected' : '' ?>><?= $e($d['domainName']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <a href="/mailing-lists/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i><?= $te('mlist.create') ?></a>
  </div>
</div>

<form method="post" action="/mailing-lists/bulk">
  <?= $csrfField ?>
  <input type="hidden" name="filterDomain" value="<?= $e($filterDomain) ?>" />
  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr>
            <th><input type="checkbox" class="form-check-input" data-select-all="selected[]" /></th>
            <th><?= $te('common.address') ?></th>
            <th><?= $te('common.name') ?></th>
            <th><?= $te('common.domain') ?></th>
            <th><?= $te('mlist.policy') ?></th>
            <th><?= $te('common.status') ?></th>
            <th class="text-end"><?= $te('common.actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($mailingLists as $ml): ?>
          <tr>
            <td><input type="checkbox" class="form-check-input" name="selected[]" value="<?= $e($ml->address) ?>" /></td>
            <td><a href="/mailing-lists/<?= $e($ml->address) ?>" class="fw-medium"><?= $e($ml->address) ?></a></td>
            <td><?= $e($ml->name) ?></td>
            <td><a href="/<?= $e($ml->domain) ?>/users"><?= $e($ml->domain) ?></a></td>
            <td><span class="badge badge-status <?= $tone('access_policy', (string) $ml->accessPolicy) ?>"><?= $e($ml->accessPolicy) ?></span></td>
            <td><?= $localize($ml->active ? 'active' : 'disabled') ?></td>
            <td>
              <div class="table-actions">
                <a href="/mailing-lists/<?= $e($ml->address) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i><?= $te('common.edit') ?></a>
                <?php /* A form cannot nest inside the bulk form: the button posts the bulk form to the delete route. */ ?>
                <button type="submit" formaction="/mailing-lists/<?= $e($ml->address) ?>/delete" formnovalidate class="btn btn-sm btn-outline-danger" data-confirm="<?= $te('mlist.delete_confirm', ['address' => $ml->address]) ?>"><i class="bi bi-trash3 me-1"></i><?= $te('common.delete') ?></button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($mailingLists)): ?>
          <tr><td colspan="7" class="text-center text-body-secondary py-4"><?= $te('mlist.empty') ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (!empty($mailingLists)): ?>
  <div class="d-flex flex-wrap gap-2 align-items-center">
    <select name="action" class="form-select form-select-sm w-auto">
      <option value=""><?= $te('common.bulk_action') ?></option>
      <option value="enable"><?= $te('common.enable') ?></option>
      <option value="disable"><?= $te('common.disable') ?></option>
      <option value="delete"><?= $te('common.delete') ?></option>
    </select>
    <div class="form-check">
      <input type="checkbox" class="form-check-input" id="keepArchive" name="keepArchive" checked />
      <label class="form-check-label small text-body-secondary" for="keepArchive"><?= $te('mlist.keep_archive') ?></label><?= $help('mlist.keep_archive') ?>
    </div>
    <button type="submit" class="btn btn-sm btn-outline-secondary" data-bulk-confirm="<?= $e(json_encode(['*' => $t('common.apply_bulk_confirm')])) ?>"><?= $te('common.apply') ?></button>
  </div>
  <?php endif; ?>
</form>

<?php if (isset($paginatedResult)): ?>
  <?php include __DIR__ . '/pagination.php'; ?>
<?php endif; ?>
