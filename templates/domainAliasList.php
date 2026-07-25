<?php $pageTitle = $t('domainalias.list_title'); ?>
<div class="container">
  <div class="row">
    <div class="col">
      <h1><?= $te('domainalias.list_title') ?></h1>

      <div class="row">
        <div class="col">
          <a href="/domain-aliases/create" class="button primary outline"><?= $te('domainalias.create') ?></a>
        </div>
      </div>

      <table class="striped">
        <thead>
          <tr>
            <th><?= $te('domainalias.alias_domain') ?></th>
            <th><?= $te('domainalias.target_domain') ?></th>
            <th><?= $te('common.status') ?></th>
            <th><?= $te('common.created') ?></th>
            <th><?= $te('common.actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($aliases as $alias): ?>
          <tr>
            <td><?= $e($alias->aliasDomain) ?></td>
            <td><a href="/<?= $e($alias->targetDomain) ?>/users"><?= $e($alias->targetDomain) ?></a></td>
            <td><?= $localize($alias->active ? 'active' : 'disabled') ?></td>
            <td><?= $e($alias->created ?? '') ?></td>
            <td>
              <form method="post" action="/domain-aliases/<?= $e($alias->aliasDomain) ?>/delete" style="display:inline" data-confirm="<?= $te('domainalias.delete_confirm', ['domain' => $alias->aliasDomain]) ?>">
                <?= $csrfField ?>
                <button type="submit" class="button error outline"><?= $te('common.delete') ?></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($aliases)): ?>
          <tr><td colspan="5" class="text-light"><?= $te('domainalias.empty') ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <?php if (isset($paginatedResult)): ?>
        <?php include __DIR__ . '/pagination.php'; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
