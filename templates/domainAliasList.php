<?php $pageTitle = $t('domainalias.list_title'); ?>
<div class="page-header">
  <h1><?= $te('domainalias.list_title') ?></h1>
  <div class="page-actions">
    <a href="/domain-aliases/create" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i><?= $te('domainalias.create') ?></a>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <th><?= $te('domainalias.alias_domain') ?></th>
          <th><?= $te('domainalias.target_domain') ?></th>
          <th><?= $te('common.status') ?></th>
          <th><?= $te('common.created') ?></th>
          <th class="text-end"><?= $te('common.actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($aliases as $alias): ?>
        <tr>
          <td class="fw-medium"><?= $e($alias->aliasDomain) ?></td>
          <td><a href="/<?= $e($alias->targetDomain) ?>/users"><?= $e($alias->targetDomain) ?></a></td>
          <td><?= $localize($alias->active ? 'active' : 'disabled') ?></td>
          <td class="text-body-secondary"><?= $e($alias->created ?? '') ?></td>
          <td>
            <form method="post" action="/domain-aliases/<?= $e($alias->aliasDomain) ?>/delete" class="table-actions" data-confirm="<?= $te('domainalias.delete_confirm', ['domain' => $alias->aliasDomain]) ?>">
              <?= $csrfField ?>
              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3 me-1"></i><?= $te('common.delete') ?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($aliases)): ?>
        <tr><td colspan="5" class="text-center text-body-secondary py-4"><?= $te('domainalias.empty') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (isset($paginatedResult)): ?>
  <?php include __DIR__ . '/pagination.php'; ?>
<?php endif; ?>
