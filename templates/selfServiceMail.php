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

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
        <tr>
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
          <td class="text-nowrap text-body-secondary"><?= $e(date('Y-m-d H:i:s', (int) $msg['time_num'])) ?></td>
          <td><?= $e($msg['from_addr'] ?? '') ?></td>
          <td><?= $e($msg['subject'] ?? '') ?></td>
          <td><?= $e($msg['spam_level'] ?? '') ?></td>
          <?php if (!$quarantine): ?>
          <?php $content = trim((string) ($msg['content'] ?? '')); ?>
          <td><span class="badge badge-status <?= $tone('mail_content', $content) ?>"><?= in_array($content, $contentTypes, true) ? $te("maillog.content_{$content}") : $e($content) ?></span></td>
          <?php endif; ?>
          <td>
            <div class="table-actions">
              <?php if ($quarantine): ?>
              <form method="post" action="<?= $e($action) ?>" data-confirm="<?= $te('self.release_confirm') ?>">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="release" />
                <input type="hidden" name="mail_id" value="<?= $e($msg['mail_id']) ?>" />
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-send-check me-1"></i><?= $te('quarantine.release') ?></button>
              </form>
              <form method="post" action="<?= $e($action) ?>" data-confirm="<?= $te('quarantine.delete_confirm') ?>">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="mail_id" value="<?= $e($msg['mail_id']) ?>" />
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3 me-1"></i><?= $te('common.delete') ?></button>
              </form>
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
        <tr><td colspan="<?= $quarantine ? 5 : 6 ?>" class="text-center text-body-secondary py-4"><?= $te($quarantine ? 'quarantine.empty' : 'maillog.empty') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/pagination.php'; ?>
