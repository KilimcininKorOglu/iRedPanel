<?php $pageTitle = $t('srs.title'); ?>
<div class="page-header">
  <div>
    <h1><?= $te('srs.title') ?></h1>
    <div class="small text-body-secondary mt-1"><?= $te('srs.intro') ?></div>
  </div>
</div>

<?php $iredapdTab = 'srs'; include __DIR__ . '/iredapdNav.php'; ?>

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
      <div class="card-header"><?= $te('srs.heading') ?></div>
      <div class="card-body">
        <label for="domains" class="form-label"><?= $te('srs.domains_label') ?></label><?= $help('srs.domains_label') ?>
        <textarea id="domains" name="domains" rows="8" class="form-control"><?= $e(implode("\n", $domains ?? [])) ?></textarea>
        <div class="form-text">example.com</div>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('common.save') ?></button>
      </div>
    </form>
  </div>
</div>
