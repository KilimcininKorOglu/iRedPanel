<?php $pageTitle = $t('wblist.rdns_title'); ?>
<div class="page-header">
  <div>
    <h1><?= $te('wblist.rdns_title') ?></h1>
    <div class="small text-body-secondary mt-1"><?= $te('wblist.rdns_intro') ?></div>
  </div>
</div>

<?php $iredapdTab = 'rdns'; include __DIR__ . '/iredapdNav.php'; ?>

<?php if (!empty($success)): ?>
<div class="alert alert-success"><?= $e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>

<form method="post">
  <?= $csrfField ?>
  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card h-100 mb-0">
        <div class="card-header"><i class="bi bi-check-circle text-success me-2"></i><?= $te('wblist.rdns_whitelist_legend') ?></div>
        <div class="card-body">
          <textarea name="whitelists" rows="8" class="form-control" placeholder="example.com&#10;trusted-relay.org" aria-label="<?= $te('wblist.rdns_whitelist_legend') ?>"><?= $e(implode("\n", $whitelists)) ?></textarea>
          <div class="form-text"><?= $te('wblist.rdns_whitelist_hint') ?></div>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card h-100 mb-0">
        <div class="card-header"><i class="bi bi-slash-circle text-danger me-2"></i><?= $te('wblist.rdns_blacklist_legend') ?></div>
        <div class="card-body">
          <textarea name="blacklists" rows="8" class="form-control" placeholder="spam-source.com" aria-label="<?= $te('wblist.rdns_blacklist_legend') ?>"><?= $e(implode("\n", $blacklists)) ?></textarea>
          <div class="form-text"><?= $te('wblist.rdns_blacklist_hint') ?></div>
        </div>
      </div>
    </div>
  </div>
  <button type="submit" class="btn btn-primary mt-4"><?= $te('common.save') ?></button>
</form>
