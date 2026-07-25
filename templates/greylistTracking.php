<?php $pageTitle = $t('greylist.tracking_title'); ?>
<div class="container">
  <div class="row">
    <div class="col">
      <h1><?= $te('greylist.tracking_title') ?></h1>
      <p class="text-light"><?= $te('greylist.tracking_intro') ?></p>

      <table class="striped">
        <thead>
          <tr>
            <th><?= $te('greylist.sender') ?></th>
            <th><?= $te('greylist.recipient') ?></th>
            <th><?= $te('greylist.client_ip') ?></th>
            <th><?= $te('greylist.init_time') ?></th>
            <th><?= $te('greylist.blocked_count') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($entries as $entry): ?>
          <tr>
            <td><?= $e($entry['sender'] ?? '') ?></td>
            <td><?= $e($entry['recipient'] ?? '') ?></td>
            <td><?= $e($entry['client_address'] ?? '') ?></td>
            <td><?= $e($entry['init_time'] ?? '') ?></td>
            <td><?= $e($entry['blocked_count'] ?? 0) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($entries)): ?>
          <tr><td colspan="5" class="text-light"><?= $te('greylist.no_tracking') ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <?php if (isset($paginatedResult)): ?>
        <?php include __DIR__ . '/pagination.php'; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
