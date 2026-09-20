<?php
$pageTitle = $t('totp.title');
$state = $enabled ? 'enabled' : ($pending ? 'pending' : 'disabled');
?>
<div class="page-header">
  <div>
    <h1><?= $te('totp.title') ?></h1>
    <div class="small text-body-secondary mt-1"><?= $e($session['email']) ?></div>
  </div>
  <div><span class="<?= $e($tone('totp', $state)) ?>"><?= $te('totp.state_' . $state) ?></span></div>
</div>

<div class="row">
  <div class="col-xl-8">
    <?php if ($required && !$enabled): ?>
    <div class="alert alert-warning"><?= $te('totp.msg_setup_required') ?></div>
    <?php endif; ?>

    <?php if ($recoveryCodes !== []): ?>
    <div class="card mb-3">
      <div class="card-header"><?= $te('totp.recovery_codes') ?><?= $help('totp.recovery_codes') ?></div>
      <div class="card-body">
        <div class="alert alert-warning"><?= $te('totp.recovery_codes_hint') ?></div>
        <ul class="list-inline mb-0">
          <?php foreach ($recoveryCodes as $code): ?>
          <li class="list-inline-item"><code class="fs-6"><?= $e($code) ?></code></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($pending): ?>
    <div class="card mb-3">
      <div class="card-header"><?= $te('totp.scan_title') ?><?= $help('totp.scan_title') ?></div>
      <div class="card-body">
        <p class="text-body-secondary"><?= $te('totp.scan_hint') ?></p>
        <div class="mb-3 bg-white d-inline-block p-2" style="width: 220px;" data-qr="<?= $e($uri) ?>"></div>
        <div class="mb-3">
          <div class="form-label"><?= $te('totp.secret') ?></div>
          <code class="fs-5"><?= $e($readableSecret) ?></code>
        </div>
        <form method="post" class="row g-2 align-items-end">
          <?= $csrfField ?>
          <input type="hidden" name="action" value="confirm" />
          <div class="col-auto">
            <label for="code" class="form-label"><?= $te('totp.code') ?></label><?= $help('totp.code') ?>
            <input type="text" id="code" name="code" class="form-control" inputmode="numeric" autocomplete="one-time-code" required autofocus />
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary"><?= $te('totp.confirm') ?></button>
          </div>
        </form>
      </div>
      <?php if (!$required): ?>
      <div class="card-footer">
        <form method="post">
          <?= $csrfField ?>
          <input type="hidden" name="action" value="cancel" />
          <button type="submit" class="btn btn-sm btn-outline-secondary"><?= $te('totp.cancel_setup') ?></button>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <?php elseif ($enabled): ?>
    <div class="card mb-3">
      <div class="card-body">
        <p><?= $te('totp.enabled_hint') ?></p>
        <p class="mb-0"><?= $te('totp.remaining_codes', ['count' => (int) $remainingCodes]) ?></p>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><?= $te('totp.change_title') ?><?= $help('totp.change_title') ?></div>
      <div class="card-body">
        <form method="post" class="row g-2 align-items-end">
          <?= $csrfField ?>
          <div class="col-auto">
            <label for="code" class="form-label"><?= $te('totp.code') ?></label><?= $help('totp.code') ?>
            <input type="text" id="code" name="code" class="form-control" inputmode="numeric" autocomplete="one-time-code" required />
          </div>
          <div class="col-auto">
            <button type="submit" name="action" value="codes" class="btn btn-outline-primary"><?= $te('totp.new_codes') ?></button>
          </div>
          <?php if (!$required): ?>
          <div class="col-auto">
            <button type="submit" name="action" value="disable" class="btn btn-outline-danger" data-confirm="<?= $te('totp.confirm_disable') ?>"><?= $te('totp.disable') ?></button>
          </div>
          <?php endif; ?>
        </form>
      </div>
    </div>
    <?php else: ?>
    <div class="card">
      <div class="card-body">
        <p><?= $te('totp.start_hint') ?></p>
        <form method="post">
          <?= $csrfField ?>
          <input type="hidden" name="action" value="start" />
          <button type="submit" class="btn btn-primary"><?= $te('totp.start') ?></button>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($pending): ?>
<script src="<?= $asset('/static/vendor/qrcode/qrcode.js') ?>"></script>
<?php endif; ?>
