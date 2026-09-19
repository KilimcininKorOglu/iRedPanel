<?php $pageTitle = $t('domainownership.title'); ?>
<div class="page-header">
  <h1><?= $te('domainownership.title') ?></h1>
</div>

<?php if (!empty($success)): ?>
<div class="alert alert-success"><?= $e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-xl-9">
    <?php if (!empty($pendingDomains)): ?>
    <div class="card">
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr>
              <th><?= $te('common.domain') ?></th>
              <th><?= $te('domainownership.verify_code') ?></th>
              <th><?= $te('common.status') ?></th>
              <th class="text-end"><?= $te('common.actions') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingDomains as $pd): ?>
            <?php $verified = (bool) (int) ($pd['verified'] ?? 0); ?>
            <tr>
              <td class="fw-medium"><?= $e($pd['domain']) ?></td>
              <td><code><?= $e($pd['verify_code']) ?></code></td>
              <td><span class="badge badge-status <?= $tone('ownership', $verified ? 'verified' : 'pending') ?>"><?= $verified ? $te('domainownership.verified') : $te('domainownership.pending') ?></span></td>
              <td>
                <?php if (!$verified): ?>
                <div class="table-actions">
                  <form method="post">
                    <?= $csrfField ?>
                    <input type="hidden" name="action" value="verify" />
                    <input type="hidden" name="domain" value="<?= $e($pd['domain']) ?>" />
                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-patch-check me-1"></i><?= $te('domainownership.verify_dns') ?></button>
                  </form>
                  <?php if (!empty($session['isGlobalAdmin'])): ?>
                  <form method="post">
                    <?= $csrfField ?>
                    <input type="hidden" name="action" value="force_verify" />
                    <input type="hidden" name="domain" value="<?= $e($pd['domain']) ?>" />
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><?= $te('domainownership.force_verify') ?></button>
                  </form>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <p class="small text-body-secondary"><?= $te('domainownership.txt_hint') ?></p>
    <?php else: ?>
    <div class="card"><div class="card-body text-center text-body-secondary py-4"><?= $te('domainownership.none') ?></div></div>
    <?php endif; ?>
  </div>
</div>
