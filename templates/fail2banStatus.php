<?php $pageTitle = $t('fail2ban.title'); ?>
<div class="page-header">
  <h1><?= $te('fail2ban.status_title') ?></h1>
</div>

<div class="row g-3 mb-4">
  <?php foreach ($jails as $jail): ?>
  <?php $ips = $bannedIps[$jail] ?? []; ?>
  <div class="col-lg-6">
    <div class="card h-100 mb-0">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-shield-lock"></i><?= $e($jail) ?>
        <span class="badge tone-badge <?= $ips === [] ? 'tone-gray' : 'tone-red' ?> ms-auto"><?= count($ips) ?></span>
      </div>
      <?php if (!empty($ips)): ?>
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr>
              <th><?= $te('fail2ban.banned_ip') ?></th>
              <th class="text-end"><?= $te('common.actions') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ips as $ip): ?>
            <tr>
              <td><code><?= $e($ip) ?></code></td>
              <td>
                <form method="post" action="/fail2ban/unban" class="table-actions" data-confirm="<?= $te('fail2ban.unban_confirm', ['ip' => $ip]) ?>">
                  <?= $csrfField ?>
                  <input type="hidden" name="jail" value="<?= $e($jail) ?>" />
                  <input type="hidden" name="ip" value="<?= $e($ip) ?>" />
                  <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-unlock me-1"></i><?= $te('fail2ban.unban') ?></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="card-body text-body-secondary"><?= $te('fail2ban.no_banned') ?></div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row">
  <div class="col-xl-8">
    <form method="post" action="/fail2ban/ban" class="card">
      <?= $csrfField ?>
      <div class="card-header"><?= $te('fail2ban.ban_heading') ?></div>
      <div class="card-body d-flex flex-wrap gap-2">
        <select name="jail" class="form-select w-auto" required aria-label="Jail">
          <?php foreach ($jails as $jail): ?>
          <option value="<?= $e($jail) ?>"><?= $e($jail) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="ip" class="form-control flex-grow-1 w-auto" placeholder="<?= $te('fail2ban.ip_placeholder') ?>" required pattern="[0-9a-fA-F.:]*" aria-label="<?= $te('fail2ban.banned_ip') ?>" />
        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-slash-circle me-1"></i><?= $te('fail2ban.ban_ip') ?></button>
      </div>
    </form>
  </div>
</div>
