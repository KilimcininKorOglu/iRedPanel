<?php $pageTitle = $t('domainownership.title'); ?>
<div class="container">
  <div class="row">
    <div class="col-8">
      <h1><?= $te('domainownership.title') ?></h1>

      <?php if (!empty($success)): ?>
      <div class="card bg-success text-white"><?= $e($success) ?></div>
      <?php endif; ?>
      <?php if (!empty($error)): ?>
      <div class="card bg-error text-white"><?= $e($error) ?></div>
      <?php endif; ?>

      <?php if (!empty($pendingDomains)): ?>
      <table class="striped">
        <thead>
          <tr>
            <th><?= $te('common.domain') ?></th>
            <th><?= $te('domainownership.verify_code') ?></th>
            <th><?= $te('common.status') ?></th>
            <th><?= $te('common.actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pendingDomains as $pd): ?>
          <tr>
            <td><?= $e($pd['domain']) ?></td>
            <td><code><?= $e($pd['verify_code']) ?></code></td>
            <td><?= (int) ($pd['verified'] ?? 0) ? $te('domainownership.verified') : $te('domainownership.pending') ?></td>
            <td>
              <?php if (!(int) ($pd['verified'] ?? 0)): ?>
              <form method="post" style="display:inline">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="verify" />
                <input type="hidden" name="domain" value="<?= $e($pd['domain']) ?>" />
                <button type="submit" class="button primary outline"><?= $te('domainownership.verify_dns') ?></button>
              </form>
              <?php if (!empty($session['isGlobalAdmin'])): ?>
              <form method="post" style="display:inline">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="force_verify" />
                <input type="hidden" name="domain" value="<?= $e($pd['domain']) ?>" />
                <button type="submit" class="button outline"><?= $te('domainownership.force_verify') ?></button>
              </form>
              <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <p class="text-light"><?= $te('domainownership.txt_hint') ?></p>
      <?php else: ?>
      <p class="text-light"><?= $te('domainownership.none') ?></p>
      <?php endif; ?>
    </div>
  </div>
</div>
