<?php $pageTitle = $t('maillog.title'); ?>
<div class="container">
  <h1><?= $te('maillog.title') ?></h1>

  <form method="get" style="margin-bottom: 1rem;">
    <div class="row">
      <div class="col-6">
        <input type="text" name="email" placeholder="<?= $te('maillog.filter_placeholder') ?>" value="<?= $e($filterEmail ?? '') ?>" />
      </div>
      <div class="col-6">
        <button type="submit" class="button outline"><?= $te('maillog.filter') ?></button>
        <a href="/amavisd/maillog" class="button outline"><?= $te('maillog.clear') ?></a>
      </div>
    </div>
  </form>

  <table class="striped">
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
      <tr>
        <td><?= $e($entry['time_iso'] ?? '') ?></td>
        <td><?= $e($entry['from_addr'] ?? '') ?></td>
        <td><?= $e($entry['recipient'] ?? '') ?></td>
        <td><?= $e($entry['subject'] ?? '') ?></td>
        <td><?= $e($entry['spam_level'] ?? '') ?></td>
        <td><?= $e($entry['content'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($entries)): ?>
      <tr><td colspan="6" class="text-light"><?= $te('maillog.empty') ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <?php if (isset($paginatedResult)): ?>
    <?php include __DIR__ . '/pagination.php'; ?>
  <?php endif; ?>
</div>
