<?php $pageTitle = $t('quarantine.title'); ?>
<div class="page-header">
  <h1><?= $te('quarantine.heading') ?></h1>
  <div class="page-actions">
    <?php /* A separate form: a form cannot nest inside the GET filter form. */ ?>
    <form id="quarantineCleanup" method="post" action="/amavisd/cleanup" data-confirm="<?= $te('quarantine.cleanup_confirm') ?>">
      <?= $csrfField ?>
      <button type="submit" class="btn btn-outline-danger"><i class="bi bi-eraser me-1"></i><?= $te('quarantine.cleanup') ?></button>
    </form>
  </div>
</div>

<form method="get" class="d-flex flex-wrap gap-2 mb-3">
  <input type="text" name="domain" class="form-control w-auto flex-grow-1" style="max-width: 420px" placeholder="<?= $te('quarantine.filter_placeholder') ?>" value="<?= $e($filterDomain ?? '') ?>" aria-label="<?= $te('quarantine.filter') ?>" />
  <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-funnel me-1"></i><?= $te('quarantine.filter') ?></button>
  <a href="/amavisd/quarantine" class="btn btn-outline-secondary"><?= $te('quarantine.clear') ?></a>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <th><?= $te('quarantine.date') ?></th>
          <th><?= $te('quarantine.from') ?></th>
          <th><?= $te('quarantine.to') ?></th>
          <th><?= $te('quarantine.subject') ?></th>
          <th><?= $te('quarantine.spam_level') ?></th>
          <th class="text-end"><?= $te('common.actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($messages as $msg): ?>
        <tr>
          <td class="text-nowrap text-body-secondary"><?= $e(date('Y-m-d H:i:s', (int) $msg['time_num'])) ?></td>
          <td><?= $e($msg['from_addr'] ?? '') ?></td>
          <td><?= $e($msg['recipient'] ?? '') ?></td>
          <td><?= $e($msg['subject'] ?? '') ?></td>
          <td><?= $e($msg['spam_level'] ?? '') ?></td>
          <td>
            <div class="table-actions">
              <form method="post" action="/amavisd/quarantine/<?= $e($msg['mail_id'] ?? '') ?>/release" data-confirm="<?= $te('quarantine.release_confirm') ?>">
                <?= $csrfField ?>
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-send-check me-1"></i><?= $te('quarantine.release') ?></button>
              </form>
              <form method="post" action="/amavisd/quarantine/<?= $e($msg['mail_id'] ?? '') ?>/delete" data-confirm="<?= $te('quarantine.delete_confirm') ?>">
                <?= $csrfField ?>
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3 me-1"></i><?= $te('common.delete') ?></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($messages)): ?>
        <tr><td colspan="6" class="text-center text-body-secondary py-4"><?= $te('quarantine.empty') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (isset($paginatedResult)): ?>
  <?php include __DIR__ . '/pagination.php'; ?>
<?php endif; ?>
