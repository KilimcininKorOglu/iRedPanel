<?php
// One template for the quarantined mail and the received mail of the user.
$quarantine = $page === 'quarantine';
// Amavisd content codes of msgs.content that have a translated label.
$contentTypes = ['C', 'S', 'Y', 'V', 'B', 'H', 'M', 'O', 'T', 'U'];
$pageTitle = $t($quarantine ? 'domain.pref_quarantine' : 'domain.pref_received');
$action = '/self/' . $page . ($paginatedResult->currentPage > 1 ? '?page=' . $paginatedResult->currentPage : '');
?>
<div class="page-header">
  <div>
    <h1><?= $e($pageTitle) ?></h1>
    <div class="small text-body-secondary mt-1"><?= $e($session['email']) ?></div>
  </div>
</div>

<?php if ($quarantine): ?>
<form method="post" action="<?= $e($action) ?>">
  <?= $csrfField ?>
<?php endif; ?>
<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <?php if ($quarantine): ?>
          <th><input type="checkbox" class="form-check-input" data-select-all="selected[]" /></th>
          <?php endif; ?>
          <th><?= $te('quarantine.date') ?></th>
          <th><?= $te('quarantine.from') ?></th>
          <th><?= $te('quarantine.subject') ?></th>
          <th><?= $te('quarantine.spam_level') ?></th>
          <?php if (!$quarantine): ?>
          <th><?= $te('common.type') ?></th>
          <?php endif; ?>
          <th class="text-end"><?= $te('common.actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($messages as $msg): ?>
        <tr>
          <?php if ($quarantine): ?>
          <td><input type="checkbox" class="form-check-input" name="selected[]" value="<?= $e($msg['mail_id']) ?>" /></td>
          <?php endif; ?>
          <td class="text-nowrap text-body-secondary"><?= $e(date('Y-m-d H:i:s', (int) $msg['time_num'])) ?></td>
          <td><?= $e($msg['from_addr'] ?? '') ?></td>
          <?php $subject = ($msg['subject'] ?? '') !== '' ? (string) $msg['subject'] : $t('quarantine.no_subject'); ?>
          <?php if ($quarantine): ?>
          <td><a href="/self/quarantine/<?= $e(rawurlencode((string) $msg['mail_id'])) ?>/view"><?= $e($subject) ?></a></td>
          <?php else: ?>
          <td><?= $e($msg['subject'] ?? '') ?></td>
          <?php endif; ?>
          <td><?= $e($msg['spam_level'] ?? '') ?></td>
          <?php if (!$quarantine): ?>
          <?php $content = trim((string) ($msg['content'] ?? '')); ?>
          <td><span class="badge badge-status <?= $tone('mail_content', $content) ?>"><?= in_array($content, $contentTypes, true) ? $te("maillog.content_{$content}") : $e($content) ?></span></td>
          <?php endif; ?>
          <td>
            <div class="table-actions">
              <?php if ($quarantine): ?>
              <?php /* The button name carries the action and its value the mail ID, so one form serves every row. */ ?>
              <button type="submit" name="release" value="<?= $e($msg['mail_id']) ?>" class="btn btn-sm btn-outline-primary" data-confirm="<?= $te('self.release_confirm') ?>"><i class="bi bi-send-check me-1"></i><?= $te('quarantine.release') ?></button>
              <button type="submit" name="delete" value="<?= $e($msg['mail_id']) ?>" class="btn btn-sm btn-outline-danger" data-confirm="<?= $te('quarantine.delete_confirm') ?>"><i class="bi bi-trash3 me-1"></i><?= $te('common.delete') ?></button>
              <?php elseif (($msg['from_addr'] ?? '') !== ''): ?>
              <form method="post" action="<?= $e($action) ?>" data-confirm="<?= $te('self.blacklist_confirm', ['sender' => (string) $msg['from_addr']]) ?>">
                <?= $csrfField ?>
                <input type="hidden" name="sender" value="<?= $e($msg['from_addr']) ?>" />
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-slash-circle me-1"></i><?= $te('self.blacklist_sender') ?></button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($messages)): ?>
        <tr><td colspan="6" class="text-center text-body-secondary py-4"><?= $te($quarantine ? 'quarantine.empty' : 'maillog.empty') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($quarantine): ?>
  <?php if (!empty($messages)): ?>
  <div class="d-flex flex-wrap gap-2 align-items-center mt-3">
    <select name="action" class="form-select form-select-sm w-auto">
      <option value=""><?= $te('common.bulk_action') ?></option>
      <option value="release"><?= $te('quarantine.release') ?></option>
      <option value="delete"><?= $te('common.delete') ?></option>
    </select>
    <button type="submit" class="btn btn-sm btn-outline-secondary" data-bulk-confirm="<?= $e(json_encode(['*' => $t('common.apply_bulk_confirm')])) ?>"><?= $te('common.apply') ?></button>
  </div>
  <?php endif; ?>
</form>
<?php endif; ?>

<?php include __DIR__ . '/pagination.php'; ?>
