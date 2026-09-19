<?php $pageTitle = $t('wblist.senderscore_title'); ?>
<div class="page-header">
  <div>
    <h1><?= $te('wblist.senderscore_title') ?></h1>
    <div class="small text-body-secondary mt-1"><?= $te('wblist.senderscore_intro') ?></div>
  </div>
</div>

<?php $iredapdTab = 'senderscore'; include __DIR__ . '/iredapdNav.php'; ?>

<?php if (!empty($success)): ?>
<div class="alert alert-success"><?= $e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-xl-8">
    <form method="post" class="card">
      <?= $csrfField ?>
      <div class="card-header"><?= $te('wblist.senderscore_legend') ?><?= $help('wblist.senderscore_legend') ?></div>
      <div class="card-body">
        <textarea name="ips" rows="10" class="form-control" placeholder="192.168.1.1&#10;10.0.0.1" aria-label="<?= $te('wblist.senderscore_legend') ?>"><?= $e(implode("\n", $ips)) ?></textarea>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('common.save') ?></button>
      </div>
    </form>
  </div>
</div>
