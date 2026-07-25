<?php $pageTitle = $t('lastlogin.title'); ?>
<div class="container">
  <div class="row">
    <div class="col">
      <h1><?= $te('lastlogin.title') ?></h1>

      <form method="get" action="/last-logins" style="margin-bottom:1rem;">
        <select name="domain" onchange="this.form.submit()">
          <option value=""><?= $te('lastlogin.all_domains') ?></option>
          <?php foreach ($domains as $d): ?>
          <option value="<?= $e($d['domain'] ?? $d['name'] ?? '') ?>"
            <?= ($filterDomain === ($d['domain'] ?? $d['name'] ?? '')) ? 'selected' : '' ?>>
            <?= $e($d['domain'] ?? $d['name'] ?? '') ?>
          </option>
          <?php endforeach; ?>
        </select>
      </form>

      <table class="striped">
        <thead>
          <tr>
            <th><?= $te('common.username') ?></th>
            <th><?= $te('common.domain') ?></th>
            <th>IMAP</th>
            <th>POP3</th>
            <th>LDA</th>
            <th>LMTP</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logins as $login): ?>
          <tr>
            <td><?= $e($login['username']) ?></td>
            <td><?= $e($login['domain']) ?></td>
            <td><?= $e($login['imap'] ?? $t('lastlogin.never')) ?></td>
            <td><?= $e($login['pop3'] ?? $t('lastlogin.never')) ?></td>
            <td><?= $e($login['lda'] ?? $t('lastlogin.never')) ?></td>
            <td><?= $e($login['lmtp'] ?? $t('lastlogin.never')) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($logins)): ?>
          <tr><td colspan="6" class="text-light"><?= $te('lastlogin.empty') ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <?php if (isset($paginatedResult)): ?>
        <?php include __DIR__ . '/pagination.php'; ?>
      <?php endif; ?>

      <p><a href="/system-settings">&larr; <?= $te('lastlogin.back_to_settings') ?></a></p>
    </div>
  </div>
</div>
