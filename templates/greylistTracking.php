<?php $pageTitle = $t('greylist.tracking_title'); ?>
<div class="page-header">
  <div>
    <h1><?= $te('greylist.tracking_title') ?></h1>
    <div class="small text-body-secondary mt-1"><?= $te('greylist.tracking_intro') ?></div>
  </div>
</div>

<?php $iredapdTab = 'tracking'; include __DIR__ . '/iredapdNav.php'; ?>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover">
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
          <td><code><?= $e($entry['client_address'] ?? '') ?></code></td>
          <td class="text-nowrap text-body-secondary"><?= $e(date('Y-m-d H:i:s', (int) $entry['init_time'])) ?></td>
          <td><?= $e($entry['blocked_count'] ?? 0) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($entries)): ?>
        <tr><td colspan="5" class="text-center text-body-secondary py-4"><?= $te('greylist.no_tracking') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (isset($paginatedResult)): ?>
  <?php include __DIR__ . '/pagination.php'; ?>
<?php endif; ?>
