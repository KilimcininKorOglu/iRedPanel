<?php
$pageTitle = $t('maillog.title');
// Amavisd content codes of msgs.content that have a translated label.
$contentTypes = ['C', 'S', 'Y', 'V', 'B', 'H', 'M', 'O', 'T', 'U'];
?>
<div class="page-header">
  <h1><?= $te('maillog.title') ?></h1>
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
  <input type="text" name="email" data-account-picker="single" class="form-control w-auto flex-grow-1" style="max-width: 420px" placeholder="<?= $te('maillog.filter_placeholder') ?>" value="<?= $e($filterEmail ?? '') ?>" aria-label="<?= $te('maillog.filter') ?>" />
  <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-funnel me-1"></i><?= $te('maillog.filter') ?></button>
  <a href="/amavisd/maillog" class="btn btn-outline-secondary"><?= $te('maillog.clear') ?></a>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <th><?= $te('maillog.date') ?></th>
          <th><?= $te('maillog.from') ?></th>
          <th><?= $te('maillog.to') ?></th>
          <th><?= $te('maillog.subject') ?></th>
          <th><?= $te('maillog.spam_level') ?></th>
          <th><?= $te('common.type') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($entries as $entry): ?>
        <?php $content = trim((string) ($entry['content'] ?? '')); ?>
        <tr>
          <td class="text-nowrap text-body-secondary"><?= $e(date('Y-m-d H:i:s', (int) $entry['time_num'])) ?></td>
          <td><?= $e($entry['from_addr'] ?? '') ?></td>
          <td><?= $e($entry['recipient'] ?? '') ?></td>
          <td><?= $e($entry['subject'] ?? '') ?></td>
          <td><?= $e($entry['spam_level'] ?? '') ?></td>
          <td><span class="badge badge-status <?= $tone('mail_content', (string) $content) ?>"><?= in_array($content, $contentTypes, true) ? $te("maillog.content_{$content}") : $e($content) ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($entries)): ?>
        <tr><td colspan="6" class="text-center text-body-secondary py-4"><?= $te('maillog.empty') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (isset($paginatedResult)): ?>
  <?php include __DIR__ . '/pagination.php'; ?>
<?php endif; ?>
