<?php $pageTitle = $t('domain.list_title'); ?>
<div class="page-header">
  <h1><?= $te('domain.list_title') ?></h1>
  <?php if ($canCreateDomain): ?>
  <div class="page-actions">
    <a href="/domains/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i><?= $te('domain.create') ?></a>
  </div>
  <?php endif; ?>
</div>

<ul class="nav nav-pills mb-3">
  <li class="nav-item"><a class="nav-link<?= empty($statusFilter) ? ' active' : '' ?>" href="/domains"><?= $te('common.all') ?></a></li>
  <li class="nav-item"><a class="nav-link<?= ($statusFilter ?? '') === 'active' ? ' active' : '' ?>" href="/domains?status=active"><?= $te('common.active') ?></a></li>
  <li class="nav-item"><a class="nav-link<?= ($statusFilter ?? '') === 'disabled' ? ' active' : '' ?>" href="/domains?status=disabled"><?= $te('common.disabled') ?></a></li>
</ul>

<form method="post" action="/domains/bulk">
  <?= $csrfField ?>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr>
            <?php if ($isGlobalAdmin): ?>
            <th class="w-auto"><input type="checkbox" class="form-check-input" id="selectAll" data-select-all="selectedDomains[]" /></th>
            <?php endif; ?>
            <th><?= $te('common.name') ?></th>
            <th><?= $te('common.description') ?></th>
            <th><?= $te('domain.users') ?></th>
            <th><?= $te('common.status') ?></th>
            <th class="text-end"><?= $te('common.actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($domains as $domain): ?>
          <tr>
            <?php if ($isGlobalAdmin): ?>
            <td><input type="checkbox" class="form-check-input" name="selectedDomains[]" value="<?= $e($domain->domainName) ?>" /></td>
            <?php endif; ?>
            <td><a href="/<?= $e($domain->domainName) ?>/users" class="fw-medium"><?= $e($domain->domainName) ?></a></td>
            <td class="text-body-secondary"><?= $e($domain->description) ?></td>
            <td><?= $e($domain->currentUserCount) ?></td>
            <td><?= $localize($domain->active ? 'active' : 'disabled') ?></td>
            <td>
              <div class="table-actions">
                <?php $openPages = \App\Models\ProfileToggles::openDomainPages(\App\Models\DomainSettings::fromSettingsString($domain->settings), $isGlobalAdmin); ?>
                <?php if ($openPages !== []): ?>
                <a href="/domains/<?= $e($domain->domainName) ?>/<?= $e($openPages[0] === 'general' ? 'edit' : $openPages[0]) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i><?= $te('common.edit') ?></a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($isGlobalAdmin): ?>
  <div class="d-flex flex-wrap gap-2 align-items-center">
    <select name="action" class="form-select form-select-sm w-auto" required>
      <option value=""><?= $te('common.bulk_action') ?></option>
      <option value="enable"><?= $te('common.enable_selected') ?></option>
      <option value="disable"><?= $te('common.disable_selected') ?></option>
      <option value="delete"><?= $te('common.delete_selected') ?></option>
    </select>
    <?php include __DIR__ . '/keepMailboxDays.php'; ?>
    <button type="submit" class="btn btn-sm btn-outline-secondary" data-bulk-confirm="<?= $e(json_encode(['delete' => $t('domain.bulk_delete_confirm')])) ?>"><?= $te('common.apply') ?></button>
  </div>
  <?php endif; ?>
</form>

<?php if (isset($paginatedResult)): ?>
  <?php include __DIR__ . '/pagination.php'; ?>
<?php endif; ?>
