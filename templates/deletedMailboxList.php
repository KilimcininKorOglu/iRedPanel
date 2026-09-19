<?php $pageTitle = $t('deletedmbx.title'); ?>
<div class="page-header">
  <h1><?= $te('deletedmbx.title') ?></h1>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <th><?= $te('common.username') ?></th>
          <th><?= $te('common.domain') ?></th>
          <th><?= $te('deletedmbx.maildir') ?></th>
          <th><?= $te('deletedmbx.deleted_by') ?></th>
          <th><?= $te('deletedmbx.scheduled_deletion') ?></th>
          <th><?= $te('common.created') ?></th>
          <th class="text-end"><?= $te('common.actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($deletedMailboxes as $mb): ?>
        <tr>
          <td class="fw-medium"><?= $e($mb->username) ?></td>
          <td><?= $e($mb->domain) ?></td>
          <td class="small text-break text-body-secondary"><?= $e($mb->maildir) ?></td>
          <td><?= $e($mb->admin) ?></td>
          <td><?= $e($mb->deleteDate ?? $t('deletedmbx.not_scheduled')) ?></td>
          <td class="text-nowrap text-body-secondary"><?= $e($mb->timestamp ?? '') ?></td>
          <td>
            <div class="table-actions">
              <form method="post" action="/deleted-mailboxes/<?= $e($mb->id) ?>/reschedule" class="d-flex gap-1">
                <?= $csrfField ?>
                <input type="date" name="newDate" class="form-control form-control-sm w-auto" required aria-label="<?= $te('deletedmbx.scheduled_deletion') ?>" />
                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-calendar-event me-1"></i><?= $te('deletedmbx.reschedule') ?></button>
              </form>
              <form method="post" action="/deleted-mailboxes/<?= $e($mb->id) ?>/cancel" data-confirm="<?= $te('deletedmbx.cancel_confirm') ?>">
                <?= $csrfField ?>
                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i><?= $te('common.cancel') ?></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($deletedMailboxes)): ?>
        <tr><td colspan="7" class="text-center text-body-secondary py-4"><?= $te('deletedmbx.empty') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (isset($paginatedResult)): ?>
  <?php include __DIR__ . '/pagination.php'; ?>
<?php endif; ?>
