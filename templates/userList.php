<?php
$pageTitle = $t('user.list_title');
$letters = range('A', 'Z');
$baseUrl = '/' . urlencode($domain) . '/users';
$sortUrl = function (string $col) use ($baseUrl, $sortBy, $sortDir, $currentLetter) {
    $newDir = ($sortBy === $col && $sortDir === 'asc') ? 'desc' : 'asc';
    $params = ['sort' => $col, 'dir' => $newDir];
    if ($currentLetter) {
        $params['letter'] = $currentLetter;
    }
    return $baseUrl . '?' . http_build_query($params);
};
$sortIcon = function (string $col) use ($sortBy, $sortDir) {
    if ($sortBy !== $col) {
        return '';
    }
    return $sortDir === 'asc' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>';
};
?>
<div class="page-header">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/domains"><?= $te('domain.list_title') ?></a></li>
        <li class="breadcrumb-item"><?= $e($domain) ?></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $te('user.list_title') ?></li>
      </ol>
    </nav>
    <h1><?= $te('user.list_title') ?></h1>
  </div>
  <div class="page-actions">
    <?php if (!empty($supportsCreate)): ?>
    <a href="/<?= $e($domain) ?>/users/create" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i><?= $te('user.create') ?></a>
    <?php endif; ?>
    <a href="/export/domain/<?= $e(rawurlencode($domain)) ?>" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv me-1"></i><?= $te('user.export_csv') ?></a>
    <a href="/export/domain/<?= $e(rawurlencode($domain)) ?>?format=json" class="btn btn-outline-secondary"><i class="bi bi-filetype-json me-1"></i><?= $te('user.export_json') ?></a>
    <?php if (!empty($supportsLdif)): ?>
    <a href="/export/ldif/<?= $e(rawurlencode($domain)) ?>" class="btn btn-outline-secondary" title="<?= $te('export.ldif_password_warning') ?>"><i class="bi bi-filetype-txt me-1"></i><?= $te('export.ldif_domain') ?></a>
    <?php endif; ?>
  </div>
</div>

<ul class="nav nav-pills mb-2">
  <li class="nav-item"><a class="nav-link<?= empty($statusFilter) ? ' active' : '' ?>" href="<?= $e($baseUrl) ?>"><?= $te('common.all') ?></a></li>
  <li class="nav-item"><a class="nav-link<?= ($statusFilter ?? '') === 'active' ? ' active' : '' ?>" href="<?= $e($baseUrl) ?>?status=active"><?= $te('common.active') ?></a></li>
  <li class="nav-item"><a class="nav-link<?= ($statusFilter ?? '') === 'disabled' ? ' active' : '' ?>" href="<?= $e($baseUrl) ?>?status=disabled"><?= $te('common.disabled') ?></a></li>
</ul>
<div class="letter-filter mb-3">
  <a href="<?= $e($baseUrl) ?>"<?= empty($currentLetter) ? ' class="active"' : '' ?>><?= $te('common.all') ?></a>
  <?php foreach ($letters as $letter): ?>
  <a href="<?= $e($baseUrl) ?>?letter=<?= $e($letter) ?>"<?= ($currentLetter ?? '') === $letter ? ' class="active"' : '' ?>><?= $letter ?></a>
  <?php endforeach; ?>
</div>

<form method="post" action="/<?= $e($domain) ?>/users/bulk">
  <?= $csrfField ?>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr>
            <th><input type="checkbox" class="form-check-input" id="selectAll" data-select-all="selectedUsers[]" /></th>
            <th><a href="<?= $e($sortUrl('uid')) ?>"><?= $te('user.identifier') ?><?= $sortIcon('uid') ?></a></th>
            <th><a href="<?= $e($sortUrl('mailQuota')) ?>"><?= $te('user.quota_mb') ?><?= $sortIcon('mailQuota') ?></a></th>
            <th><?= $te('user.used') ?></th>
            <th><?= $te('user.password_last_change') ?></th>
            <th><?= $te('user.expired_date') ?></th>
            <th><?= $te('admin.global_admin') ?></th>
            <th><a href="<?= $e($sortUrl('accountStatus')) ?>"><?= $te('common.status') ?><?= $sortIcon('accountStatus') ?></a></th>
            <th class="text-end"><?= $te('common.actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $user): ?>
          <?php
            $email = $user->uid . '@' . $domain;
            $usedBytes = ($usedQuotas[$email]['bytes'] ?? 0);
            $usedMb = $usedBytes > 0 ? (int) ($usedBytes / 1048576) : 0;
            $expiryState = \App\Utils\ExpiryDate::state($user->expiredDate);
          ?>
          <tr>
            <td><input type="checkbox" class="form-check-input" name="selectedUsers[]" value="<?= $e($user->uid) ?>" /></td>
            <td><a href="/<?= $e($domain) ?>/users/<?= $e($user->uid) ?>/general" class="fw-medium"><?= $e($user->uid) ?></a></td>
            <td><?= $e($user->mailQuota === 0 ? $t('common.unlimited') : $user->mailQuota) ?></td>
            <td><?= $e($usedMb) ?> MB</td>
            <td><?= $e($user->passwordLastChange ?? $t('common.none')) ?></td>
            <td>
              <?php if ($expiryState === ''): ?>
                <span class="text-secondary"><?= $te('common.none') ?></span>
              <?php else: ?>
                <span class="<?= $e($tone('expiry', $expiryState)) ?>"><?= $e($user->expiredDate) ?></span>
              <?php endif; ?>
            </td>
            <td><?= $localize($user->domainGlobalAdmin) ?></td>
            <td><?= $localize($user->accountStatus) ?></td>
            <td>
              <div class="table-actions">
                <a href="/<?= $e($domain) ?>/users/<?= $e($user->uid) ?>/general" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i><?= $te('common.edit') ?></a>
                <?php /* A form cannot nest inside the bulk form: the button posts the bulk form to the delete route. */ ?>
                <button type="submit" formaction="/<?= $e($domain) ?>/users/<?= $e($user->uid) ?>/delete" formnovalidate class="btn btn-sm btn-outline-danger" data-confirm="<?= $te('user.delete_confirm', ['uid' => $user->uid]) ?>"><i class="bi bi-trash3 me-1"></i><?= $te('common.delete') ?></button>
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
      <option value="language"><?= $te('user.language') ?></option>
      <option value="password"><?= $te('common.password') ?></option>
      <?php if (!empty($session['isGlobalAdmin'])): ?>
      <option value="transport"><?= $te('user.transport') ?></option>
      <?php endif; ?>
    </select>
    <select name="bulkLanguage" class="form-select form-select-sm w-auto" aria-label="<?= $te('user.language') ?>">
      <option value=""><?= $te('user.language_default') ?></option>
      <?php foreach ($availableLocales as $code => $name): ?>
      <option value="<?= $e($code) ?>"><?= $e($name) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="password" name="bulkPassword" autocomplete="new-password" class="form-control form-control-sm w-auto" placeholder="<?= $te('common.password') ?>" />
    <?php if (!empty($session['isGlobalAdmin'])): ?>
    <input type="text" name="bulkTransport" class="form-control form-control-sm w-auto font-monospace" placeholder="<?= $te('user.transport') ?>" />
    <?php endif; ?>
    <?php include __DIR__ . '/keepMailboxDays.php'; ?>
    <button type="submit" class="btn btn-sm btn-outline-secondary" data-bulk-confirm="<?= $e(json_encode(['delete' => $t('user.bulk_delete_confirm')])) ?>"><?= $te('common.apply') ?></button>
  </div>
</form>

<?php if (isset($paginatedResult)): ?>
  <?php include __DIR__ . '/pagination.php'; ?>
<?php endif; ?>
