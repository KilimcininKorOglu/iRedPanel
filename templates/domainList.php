<?php $pageTitle = $t('domain.list_title'); ?>
<div class="container">
  <div class="row">
    <div class="col">
      <h1><?= $te('domain.list_title') ?></h1>

      <?php if ($isGlobalAdmin): ?>
      <div class="row">
        <div class="col">
          <a href="/domains/create" class="button primary outline"><?= $te('domain.create') ?></a>
        </div>
      </div>
      <?php endif; ?>

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
            <?php if ($isGlobalAdmin): ?>
            <th><input type="checkbox" id="selectAll" data-select-all="selectedDomains[]" /></th>
            <?php endif; ?>
            <th><?= $te('common.name') ?></th>
            <th><?= $te('common.description') ?></th>
            <th><?= $te('domain.users') ?></th>
            <th><?= $te('common.status') ?></th>
            <?php if ($isGlobalAdmin): ?>
            <th><?= $te('common.actions') ?></th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($domains as $domain): ?>
          <tr>
            <?php if ($isGlobalAdmin): ?>
            <td><input type="checkbox" name="selectedDomains[]" value="<?= $e($domain->domainName) ?>" /></td>
            <?php endif; ?>
            <td>
              <a href="/<?= $e($domain->domainName) ?>/users"><?= $e($domain->domainName) ?></a>
            </td>
            <td><?= $e($domain->description) ?></td>
            <td><?= $e($domain->currentUserCount) ?></td>
            <td><?= $localize($domain->active ? 'active' : 'disabled') ?></td>
            <?php if ($isGlobalAdmin): ?>
            <td>
              <a href="/domains/<?= $e($domain->domainName) ?>/edit" class="button primary outline"><?= $te('common.edit') ?></a>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($isGlobalAdmin): ?>
      <div style="margin-top: 0.5rem;">
        <select name="action" required>
          <option value=""><?= $te('common.bulk_action') ?></option>
          <option value="enable"><?= $te('common.enable_selected') ?></option>
          <option value="disable"><?= $te('common.disable_selected') ?></option>
          <option value="delete"><?= $te('common.delete_selected') ?></option>
        </select>
        <button type="submit" class="button outline" data-bulk-confirm="<?= $e(json_encode(['delete' => $t('domain.bulk_delete_confirm')])) ?>"><?= $te('common.apply') ?></button>
      </div>
      <?php endif; ?>
      </form>

      <?php if (isset($paginatedResult)): ?>
        <?php include __DIR__ . '/pagination.php'; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
