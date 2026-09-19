<?php $pageTitle = $t('nav.group_self_service'); ?>
<div class="page-header">
  <div>
    <h1><?= $te('nav.group_self_service') ?></h1>
    <div class="small text-body-secondary mt-1"><?= $e($session['email']) ?></div>
  </div>
</div>

<div class="alert alert-info"><?= $te('self.no_pages') ?></div>
