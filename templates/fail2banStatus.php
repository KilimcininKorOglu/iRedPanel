<?php $pageTitle = $t('fail2ban.title'); ?>
<div class="container">
  <h1><?= $te('fail2ban.status_title') ?></h1>

  <?php foreach ($jails as $jail): ?>
  <div class="card" style="margin-bottom: 1rem;">
    <header><h3><?= $e($jail) ?></h3></header>

    <?php $ips = $bannedIps[$jail] ?? []; ?>
    <?php if (!empty($ips)): ?>
    <table class="striped">
      <thead>
        <tr>
          <th><?= $te('fail2ban.banned_ip') ?></th>
          <th><?= $te('common.actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ips as $ip): ?>
        <tr>
          <td><?= $e($ip) ?></td>
          <td>
            <form method="post" action="/fail2ban/unban" style="display:inline" data-confirm="<?= $te('fail2ban.unban_confirm', ['ip' => $ip]) ?>">
              <?= $csrfField ?>
              <input type="hidden" name="jail" value="<?= $e($jail) ?>" />
              <input type="hidden" name="ip" value="<?= $e($ip) ?>" />
              <button type="submit" class="button outline"><?= $te('fail2ban.unban') ?></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <p class="text-light"><?= $te('fail2ban.no_banned') ?></p>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <div class="card">
    <header><h3><?= $te('fail2ban.ban_heading') ?></h3></header>
    <form method="post" action="/fail2ban/ban">
      <?= $csrfField ?>
      <div class="row">
        <div class="col-4">
          <select name="jail" required>
            <?php foreach ($jails as $jail): ?>
            <option value="<?= $e($jail) ?>"><?= $e($jail) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-4">
          <input type="text" name="ip" placeholder="<?= $te('fail2ban.ip_placeholder') ?>" required pattern="[0-9a-fA-F.:]*" />
        </div>
        <div class="col-4">
          <button type="submit" class="button error outline"><?= $te('fail2ban.ban_ip') ?></button>
        </div>
      </div>
    </form>
  </div>
</div>
