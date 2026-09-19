<?php $pageTitle = $t('greylist.domains_title'); ?>
<div class="page-header">
  <div>
    <h1><?= $te('greylist.domains_title') ?></h1>
    <div class="small text-body-secondary mt-1"><?= $te('greylist.domains_intro') ?></div>
  </div>
</div>

<?php $iredapdTab = 'domains'; include __DIR__ . '/iredapdNav.php'; ?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
<div class="alert alert-success"><?= $e($success) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-xl-8">
    <form method="post" class="card">
      <?= $csrfField ?>
      <div class="card-header"><?= $te('greylist.domains_heading') ?></div>
      <div class="card-body">
        <label for="domains" class="form-label"><?= $te('greylist.domains_label') ?></label>
        <textarea id="domains" name="domains" rows="8" class="form-control"><?= $e(implode("\n", $domains ?? [])) ?></textarea>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('common.save') ?></button>
      </div>
    </form>

    <div class="card">
      <div class="card-header"><?= $te('greylist.spf_heading') ?></div>
      <?php if (!empty($spfSenders)): ?>
      <div class="table-responsive">
        <table class="table table-striped">
          <thead>
            <tr>
              <th><?= $te('common.domain') ?></th>
              <th><?= $te('greylist.sender') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($spfSenders as $domain => $senders): ?>
            <tr>
              <td class="fw-medium"><?= $e((string) $domain) ?></td>
              <td><?= $e(implode(', ', $senders)) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="card-body text-body-secondary"><?= $te('greylist.no_spf_senders') ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>
