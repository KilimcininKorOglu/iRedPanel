<?php $pageTitle = $t('quarantine.title'); ?>
<div class="page-header">
  <h1><?= $te('quarantine.heading') ?></h1>
  <?php if (!empty($session['isGlobalAdmin'])): ?>
  <div class="page-actions">
    <?php /* A separate form: a form cannot nest inside the GET filter form. */ ?>
    <form id="quarantineCleanup" method="post" action="/amavisd/cleanup" data-confirm="<?= $te('quarantine.cleanup_confirm') ?>">
      <?= $csrfField ?>
      <button type="submit" class="btn btn-outline-danger"><i class="bi bi-eraser me-1"></i><?= $te('quarantine.cleanup') ?></button>
    </form>
  </div>
  <?php endif; ?>
</div>

<form method="get" class="d-flex flex-wrap gap-2 mb-3">
  <select name="domain" class="form-select w-auto" data-autosubmit aria-label="<?= $te('common.domain') ?>">
    <?php if (!empty($session['isGlobalAdmin'])): ?>
    <option value=""><?= $te('common.all_domains') ?></option>
    <?php endif; ?>
    <?php foreach ($domains as $d): ?>
    <option value="<?= $e($d['domainName']) ?>"<?= ($filterDomain === $d['domainName']) ? ' selected' : '' ?>><?= $e($d['domainName']) ?></option>
    <?php endforeach; ?>
  </select>
</form>

<form method="post" action="/amavisd/quarantine/bulk">
  <?= $csrfField ?>
  <input type="hidden" name="filterDomain" value="<?= $e($filterDomain) ?>" />
<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <th><input type="checkbox" class="form-check-input" data-select-all="selected[]" /></th>
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
          <td><input type="checkbox" class="form-check-input" name="selected[]" value="<?= $e($msg['mail_id'] ?? '') ?>" /></td>
          <td class="text-nowrap text-body-secondary"><?= $e(date('Y-m-d H:i:s', (int) $msg['time_num'])) ?></td>
          <td><?= $e($msg['from_addr'] ?? '') ?></td>
          <td><?= $e($msg['recipient'] ?? '') ?></td>
          <?php $viewUrl = '/amavisd/quarantine/' . rawurlencode((string) ($msg['mail_id'] ?? '')) . '/view' . ($filterDomain !== '' ? '?domain=' . rawurlencode($filterDomain) : ''); ?>
          <td><a href="<?= $e($viewUrl) ?>"><?= $e(($msg['subject'] ?? '') !== '' ? $msg['subject'] : $t('quarantine.no_subject')) ?></a></td>
          <td><?= $e($msg['spam_level'] ?? '') ?></td>
          <td>
            <div class="table-actions">
              <?php /* A form cannot nest inside the bulk form: the buttons post the bulk form to the row route. */ ?>
              <button type="submit" formaction="/amavisd/quarantine/<?= $e($msg['mail_id'] ?? '') ?>/release" formnovalidate class="btn btn-sm btn-outline-primary" data-confirm="<?= $te('quarantine.release_confirm') ?>"><i class="bi bi-send-check me-1"></i><?= $te('quarantine.release') ?></button>
              <button type="submit" formaction="/amavisd/quarantine/<?= $e($msg['mail_id'] ?? '') ?>/delete" formnovalidate class="btn btn-sm btn-outline-danger" data-confirm="<?= $te('quarantine.delete_confirm') ?>"><i class="bi bi-trash3 me-1"></i><?= $te('common.delete') ?></button>
              <?php $sender = trim((string) ($msg['from_addr'] ?? ''), '<>'); ?>
              <?php if ($sender !== ''): ?>
              <button type="submit" name="wb" value="W" formaction="/amavisd/quarantine/<?= $e($msg['mail_id'] ?? '') ?>/wblist" formnovalidate class="btn btn-sm btn-outline-success" data-confirm="<?= $te('quarantine.whitelist_confirm', ['sender' => $sender]) ?>"><i class="bi bi-check-circle me-1"></i><?= $te('quarantine.whitelist_sender') ?></button>
              <button type="submit" name="wb" value="B" formaction="/amavisd/quarantine/<?= $e($msg['mail_id'] ?? '') ?>/wblist" formnovalidate class="btn btn-sm btn-outline-danger" data-confirm="<?= $te('quarantine.blacklist_confirm', ['sender' => $sender]) ?>"><i class="bi bi-slash-circle me-1"></i><?= $te('quarantine.blacklist_sender') ?></button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($messages)): ?>
        <tr><td colspan="7" class="text-center text-body-secondary py-4"><?= $te('quarantine.empty') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

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

<?php if (isset($paginatedResult)): ?>
  <?php include __DIR__ . '/pagination.php'; ?>
<?php endif; ?>
