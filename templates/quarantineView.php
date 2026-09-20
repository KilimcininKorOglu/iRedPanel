<?php
// One template for the admin quarantine and the self-service quarantine.
// The message is never rendered as HTML: every part goes through $e().
$pageTitle = $t('quarantine.view_title');
?>
<div class="page-header">
  <div>
    <h1><?= $te('quarantine.view_title') ?></h1>
    <div class="small text-body-secondary mt-1"><?= $e($mailId) ?></div>
  </div>
  <div class="page-actions">
    <a href="<?= $e($downloadUrl) ?>" class="btn btn-outline-primary"><i class="bi bi-download me-1"></i><?= $te('quarantine.download') ?></a>
    <a href="<?= $e($backUrl) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i><?= $te('common.back') ?></a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header"><?= $te('quarantine.headers') ?></div>
  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0">
      <tbody>
        <?php foreach ($shownHeaders as $name): ?>
        <?php if (($headers[$name] ?? '') !== ''): ?>
        <tr>
          <th class="text-nowrap w-25"><?= $e($name) ?></th>
          <td class="text-break"><?= $e($headers[$name]) ?></td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header"><?= $te('quarantine.raw_headers') ?></div>
  <div class="card-body">
    <pre class="mb-0 small text-break" style="white-space: pre-wrap;"><?= $e($headerText) ?></pre>
  </div>
</div>

<div class="card">
  <div class="card-header"><?= $te('quarantine.body') ?></div>
  <div class="card-body">
    <?php if ($body === ''): ?>
    <p class="mb-0 text-body-secondary"><?= $te('quarantine.body_empty') ?></p>
    <?php else: ?>
    <pre class="mb-0 small text-break" style="white-space: pre-wrap;"><?= $e($body) ?></pre>
    <?php if ($bodyTruncated): ?>
    <p class="mt-3 mb-0 small text-body-secondary"><?= $te('quarantine.body_truncated') ?></p>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
