<?php $pageTitle = $t('user.email_forwarding'); ?>
<div class="page-header">
  <div>
    <h1><?= $te('user.email_forwarding') ?></h1>
    <div class="small text-body-secondary mt-1"><?= $e($session['email']) ?></div>
  </div>
</div>

<div class="row">
  <div class="col-xl-8">
    <form method="post" class="card">
      <?= $csrfField ?>
      <div class="card-body">
        <div class="mb-3">
          <label for="forwardingAddresses" class="form-label"><?= $te('user.forwarding_addresses') ?></label>
          <textarea id="forwardingAddresses" name="forwardingAddresses" rows="5" class="form-control" placeholder="user@example.com"><?= $e(implode("\n", $forwardings)) ?></textarea>
        </div>
        <div class="form-check form-switch">
          <input type="checkbox" class="form-check-input" id="keepCopy" name="keepCopy" <?= $keepCopy ? 'checked' : '' ?> />
          <label class="form-check-label" for="keepCopy"><?= $te('user.keep_copy') ?></label>
        </div>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('user.save_forwarding') ?></button>
      </div>
    </form>
  </div>
</div>
