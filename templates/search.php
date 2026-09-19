<?php
$pageTitle = $t('search.title');
$typeOptions = [
    'domain' => $t('domain.list_title'),
    'user' => $t('user.list_title'),
    'alias' => $t('search.type_aliases'),
    'ml' => $t('mlist.list_title'),
    'admin' => $t('admin.list_title'),
];
$status = fn (array $row): string => $localize(($row['active'] ?? 1) ? 'active' : 'disabled');
?>
<div class="page-header">
  <h1><?= $te('search.title') ?></h1>
</div>

<form method="get" action="/search" class="card">
  <div class="card-body">
    <div class="input-group mb-3">
      <span class="input-group-text"><i class="bi bi-search"></i></span>
      <input type="text" name="q" class="form-control" value="<?= $e($query) ?>" placeholder="<?= $te('search.placeholder') ?>" autofocus aria-label="<?= $te('search.title') ?>" />
      <button type="submit" class="btn btn-primary"><?= $te('search.title') ?></button>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <span class="small text-body-secondary me-1" title="<?= $te('search.all_types') ?>"><?= $te('common.type') ?>:</span>
      <?php foreach ($typeOptions as $value => $label): ?>
      <input type="checkbox" class="btn-check" name="accountType[]" value="<?= $value ?>" id="type-<?= $value ?>" autocomplete="off"<?= in_array($value, $accountTypes, true) ? ' checked' : '' ?> />
      <label class="btn btn-sm btn-outline-secondary" for="type-<?= $value ?>"><?= $e($label) ?></label>
      <?php endforeach; ?>
    </div>
  </div>
</form>

<?php if ($results !== null): ?>
<?php
  $totalResults = count($results['domains'] ?? []) + count($results['users'] ?? [])
    + count($results['aliases'] ?? []) + count($results['mailingLists'] ?? [])
    + count($results['admins'] ?? []);
?>
<p class="text-body-secondary"><?= $te('search.results_for', ['count' => $totalResults, 'query' => $query]) ?></p>

<?php if (!empty($results['domains'])): ?>
<div class="card">
  <div class="card-header"><?= $te('domain.list_title') ?> <span class="badge <?= $tone('count', 'domains') ?>"><?= count($results['domains']) ?></span></div>
  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead><tr><th><?= $te('common.domain') ?></th><th><?= $te('common.description') ?></th><th><?= $te('common.status') ?></th></tr></thead>
      <tbody>
        <?php foreach ($results['domains'] as $d): ?>
        <tr>
          <td><a href="/domains/<?= $e($d['domain']) ?>/edit" class="fw-medium"><?= $e($d['domain']) ?></a></td>
          <td><?= $e($d['description'] ?? '') ?></td>
          <td><?= $status($d) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($results['users'])): ?>
<div class="card">
  <div class="card-header"><?= $te('user.list_title') ?> <span class="badge <?= $tone('count', 'users') ?>"><?= count($results['users']) ?></span></div>
  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead><tr><th><?= $te('common.email') ?></th><th><?= $te('common.name') ?></th><th><?= $te('common.domain') ?></th><th><?= $te('common.status') ?></th></tr></thead>
      <tbody>
        <?php foreach ($results['users'] as $u): ?>
        <?php $uid = str_contains($u['username'], '@') ? explode('@', $u['username'])[0] : $u['username']; ?>
        <tr>
          <td><a href="/<?= $e($u['domain']) ?>/users/<?= $e($uid) ?>/general" class="fw-medium"><?= $e($u['username']) ?></a></td>
          <td><?= $e($u['name'] ?? '') ?></td>
          <td><?= $e($u['domain'] ?? '') ?></td>
          <td><?= $status($u) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($results['aliases'])): ?>
<div class="card">
  <div class="card-header"><?= $te('search.type_aliases') ?> <span class="badge <?= $tone('count', 'aliases') ?>"><?= count($results['aliases']) ?></span></div>
  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead><tr><th><?= $te('common.address') ?></th><th><?= $te('common.name') ?></th><th><?= $te('common.domain') ?></th><th><?= $te('common.status') ?></th></tr></thead>
      <tbody>
        <?php foreach ($results['aliases'] as $a): ?>
        <tr>
          <td><a href="/aliases/<?= $e($a['address']) ?>" class="fw-medium"><?= $e($a['address']) ?></a></td>
          <td><?= $e($a['name'] ?? '') ?></td>
          <td><?= $e($a['domain'] ?? '') ?></td>
          <td><?= $status($a) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($results['mailingLists'])): ?>
<div class="card">
  <div class="card-header"><?= $te('mlist.list_title') ?> <span class="badge <?= $tone('count', 'mailing_lists') ?>"><?= count($results['mailingLists']) ?></span></div>
  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead><tr><th><?= $te('common.address') ?></th><th><?= $te('common.name') ?></th><th><?= $te('common.domain') ?></th><th><?= $te('common.status') ?></th></tr></thead>
      <tbody>
        <?php foreach ($results['mailingLists'] as $ml): ?>
        <tr>
          <td><a href="/mailing-lists/<?= $e($ml['address']) ?>" class="fw-medium"><?= $e($ml['address']) ?></a></td>
          <td><?= $e($ml['name'] ?? '') ?></td>
          <td><?= $e($ml['domain'] ?? '') ?></td>
          <td><?= $status($ml) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($results['admins'])): ?>
<div class="card">
  <div class="card-header"><?= $te('admin.list_title') ?> <span class="badge <?= $tone('count', 'admins') ?>"><?= count($results['admins']) ?></span></div>
  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead><tr><th><?= $te('common.email') ?></th><th><?= $te('common.name') ?></th><th><?= $te('common.status') ?></th></tr></thead>
      <tbody>
        <?php foreach ($results['admins'] as $adm): ?>
        <tr>
          <td><a href="/admins/<?= $e($adm['username']) ?>/general" class="fw-medium"><?= $e($adm['username']) ?></a></td>
          <td><?= $e($adm['name'] ?? '') ?></td>
          <td><?= $status($adm) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($totalResults === 0): ?>
<div class="card"><div class="card-body text-center text-body-secondary py-4"><?= $te('common.no_results') ?></div></div>
<?php endif; ?>
<?php endif; ?>
