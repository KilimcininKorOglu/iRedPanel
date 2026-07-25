<?php $pageTitle = $t('deletedmbx.title'); ?>
<div class="container">
  <h1><?= $te('deletedmbx.title') ?></h1>

  <table class="striped">
    <thead>
      <tr>
        <th><?= $te('common.username') ?></th>
        <th><?= $te('common.domain') ?></th>
        <th><?= $te('deletedmbx.maildir') ?></th>
        <th><?= $te('deletedmbx.deleted_by') ?></th>
        <th><?= $te('deletedmbx.scheduled_deletion') ?></th>
        <th><?= $te('common.created') ?></th>
        <th><?= $te('common.actions') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($deletedMailboxes as $mb): ?>
      <tr>
        <td><?= $e($mb->username) ?></td>
        <td><?= $e($mb->domain) ?></td>
        <td style="font-size:0.85em; word-break:break-all;"><?= $e($mb->maildir) ?></td>
        <td><?= $e($mb->admin) ?></td>
        <td><?= $e($mb->deleteDate ?? $t('deletedmbx.not_scheduled')) ?></td>
        <td><?= $e($mb->timestamp ?? '') ?></td>
        <td>
          <form method="post" action="/deleted-mailboxes/<?= $e($mb->id) ?>/cancel" style="display:inline" data-confirm="<?= $te('deletedmbx.cancel_confirm') ?>">
            <?= $csrfField ?>
            <button type="submit" class="button outline"><?= $te('common.cancel') ?></button>
          </form>
          <form method="post" action="/deleted-mailboxes/<?= $e($mb->id) ?>/reschedule" style="display:inline">
            <?= $csrfField ?>
            <input type="date" name="newDate" required style="width:auto; display:inline-block;" />
            <button type="submit" class="button outline"><?= $te('deletedmbx.reschedule') ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($deletedMailboxes)): ?>
      <tr><td colspan="7" class="text-light"><?= $te('deletedmbx.empty') ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <?php if (isset($paginatedResult)): ?>
    <?php include __DIR__ . '/pagination.php'; ?>
  <?php endif; ?>
</div>
