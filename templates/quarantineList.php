<?php $pageTitle = $t('quarantine.title'); ?>
<div class="container">
  <h1><?= $te('quarantine.heading') ?></h1>

  <form method="get" style="margin-bottom: 1rem;">
    <div class="row">
      <div class="col-6">
        <input type="text" name="domain" placeholder="<?= $te('quarantine.filter_placeholder') ?>" value="<?= $e($filterDomain ?? '') ?>" />
      </div>
      <div class="col-6">
        <button type="submit" class="button outline"><?= $te('quarantine.filter') ?></button>
        <a href="/amavisd/quarantine" class="button outline"><?= $te('quarantine.clear') ?></a>
        <form method="post" action="/amavisd/cleanup" style="display:inline" data-confirm="<?= $te('quarantine.cleanup_confirm') ?>">
          <?= $csrfField ?>
          <button type="submit" class="button error outline"><?= $te('quarantine.cleanup') ?></button>
        </form>
      </div>
    </div>
  </form>

  <table class="striped">
    <thead>
      <tr>
        <th><?= $te('quarantine.date') ?></th>
        <th><?= $te('quarantine.from') ?></th>
        <th><?= $te('quarantine.to') ?></th>
        <th><?= $te('quarantine.subject') ?></th>
        <th><?= $te('quarantine.spam_level') ?></th>
        <th><?= $te('common.actions') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($messages as $msg): ?>
      <tr>
        <td><?= $e($msg['time_iso'] ?? '') ?></td>
        <td><?= $e($msg['from_addr'] ?? '') ?></td>
        <td><?= $e($msg['recipient'] ?? '') ?></td>
        <td><?= $e($msg['subject'] ?? '') ?></td>
        <td><?= $e($msg['spam_level'] ?? '') ?></td>
        <td>
          <form method="post" action="/amavisd/quarantine/<?= $e($msg['mail_id'] ?? '') ?>/release" style="display:inline" data-confirm="<?= $te('quarantine.release_confirm') ?>">
            <?= $csrfField ?>
            <button type="submit" class="button primary outline"><?= $te('quarantine.release') ?></button>
          </form>
          <form method="post" action="/amavisd/quarantine/<?= $e($msg['mail_id'] ?? '') ?>/delete" style="display:inline" data-confirm="<?= $te('quarantine.delete_confirm') ?>">
            <?= $csrfField ?>
            <button type="submit" class="button error outline"><?= $te('common.delete') ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($messages)): ?>
      <tr><td colspan="6" class="text-light"><?= $te('quarantine.empty') ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <?php if (isset($paginatedResult)): ?>
    <?php include __DIR__ . '/pagination.php'; ?>
  <?php endif; ?>
</div>
