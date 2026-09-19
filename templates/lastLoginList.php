<?php $pageTitle = $t('lastlogin.title'); ?>
<div class="page-header">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/system-settings"><?= $te('sysset.title') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $te('lastlogin.title') ?></li>
      </ol>
    </nav>
    <h1><?= $te('lastlogin.title') ?></h1>
  </div>
  <div class="page-actions">
    <form method="get" action="/last-logins">
      <select name="domain" class="form-select" data-autosubmit aria-label="<?= $te('common.domain') ?>">
        <option value=""><?= $te('lastlogin.all_domains') ?></option>
        <?php foreach ($domains as $d): ?>
        <option value="<?= $e($d['domainName']) ?>"<?= ($filterDomain === $d['domainName']) ? ' selected' : '' ?>><?= $e($d['domainName']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover">
      <thead>
        <tr>
          <th><?= $te('common.username') ?></th>
          <th><?= $te('common.domain') ?></th>
          <th>IMAP</th>
          <th>POP3</th>
          <th>LDA</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logins as $login): ?>
        <tr>
          <td class="fw-medium"><?= $e($login['username']) ?></td>
          <td><?= $e($login['domain']) ?></td>
          <td class="text-body-secondary"><?= $e($login['imap'] ?? $t('lastlogin.never')) ?></td>
          <td class="text-body-secondary"><?= $e($login['pop3'] ?? $t('lastlogin.never')) ?></td>
          <td class="text-body-secondary"><?= $e($login['lda'] ?? $t('lastlogin.never')) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($logins)): ?>
        <tr><td colspan="5" class="text-center text-body-secondary py-4"><?= $te('lastlogin.empty') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (isset($paginatedResult)): ?>
  <?php include __DIR__ . '/pagination.php'; ?>
<?php endif; ?>

<a href="/system-settings" class="d-inline-block mt-3"><i class="bi bi-arrow-left me-1"></i><?= $te('lastlogin.back_to_settings') ?></a>
