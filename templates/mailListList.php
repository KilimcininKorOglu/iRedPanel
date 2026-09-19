<?php $pageTitle = $t('maillist.list_title'); ?>
<div class="page-header">
  <h1><?= $te('maillist.list_title') ?></h1>
  <div class="page-actions">
    <form method="get" action="/mail-lists" class="d-flex gap-2">
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
    <a href="/mail-lists/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i><?= $te('maillist.create_title') ?></a>
  </div>
</div>

<div class="small text-body-secondary mb-3"><?= $te('maillist.intro') ?></div>

<form method="post">
  <?= $csrfField ?>
  <input type="hidden" name="filterDomain" value="<?= $e($filterDomain) ?>" />
  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr>
            <th><?= $te('common.address') ?></th>
            <th><?= $te('common.name') ?></th>
            <th><?= $te('common.domain') ?></th>
            <th><?= $te('alias.access_policy') ?></th>
            <th><?= $te('common.status') ?></th>
            <th class="text-end"><?= $te('common.actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($mailLists as $list): ?>
          <tr>
            <td><a href="/mail-lists/<?= $e($list->address) ?>" class="fw-medium"><?= $e($list->address) ?></a></td>
            <td><?= $e($list->name) ?></td>
            <td><a href="/<?= $e($list->domain) ?>/users"><?= $e($list->domain) ?></a></td>
            <td><span class="badge badge-status <?= $tone('access_policy', (string) $list->accessPolicy) ?>"><?= $e($list->accessPolicy) ?></span></td>
            <td><?= $localize($list->active ? 'active' : 'disabled') ?></td>
            <td>
              <div class="table-actions">
                <a href="/mail-lists/<?= $e($list->address) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i><?= $te('common.edit') ?></a>
                <button type="submit" formaction="/mail-lists/<?= $e($list->address) ?>/delete" formnovalidate class="btn btn-sm btn-outline-danger" data-confirm="<?= $te('maillist.delete_confirm', ['address' => $list->address]) ?>"><i class="bi bi-trash3 me-1"></i><?= $te('common.delete') ?></button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($mailLists)): ?>
          <tr><td colspan="6" class="text-center text-body-secondary py-4"><?= $te('maillist.empty') ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</form>

<?php if (isset($paginatedResult)): ?>
  <?php include __DIR__ . '/pagination.php'; ?>
<?php endif; ?>
