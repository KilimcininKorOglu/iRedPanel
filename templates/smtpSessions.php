<?php $pageTitle = $t('smtpsession.title'); ?>
<div class="page-header">
  <div>
    <h1><?= $te('smtpsession.title') ?></h1>
    <div class="small text-body-secondary mt-1"><?= $te('smtpsession.intro') ?></div>
  </div>
</div>

<?php $iredapdTab = 'sessions'; include __DIR__ . '/iredapdNav.php'; ?>

<form method="get" class="d-flex flex-wrap gap-2 my-3">
  <div>
    <label for="action" class="form-label"><?= $te('smtpsession.action') ?></label><?= $help('smtpsession.action') ?>
    <select id="action" name="action" class="form-select w-auto" data-autosubmit>
      <option value=""><?= $te('smtpsession.all_actions') ?></option>
      <?php foreach ($actions as $a): ?>
      <option value="<?= $e($a) ?>"<?= ($filterAction === $a) ? ' selected' : '' ?> title="<?= $e($actionHelp($a)) ?>"><?= $e($a) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="flex-grow-1" style="max-width: 360px">
    <label for="email" class="form-label"><?= $te('smtpsession.filter_email') ?></label><?= $help('smtpsession.filter_email') ?>
    <input type="text" id="email" name="email" class="form-control" value="<?= $e($filterEmail) ?>" placeholder="user@example.com" />
  </div>
  <div>
    <label for="ip" class="form-label"><?= $te('smtpsession.filter_ip') ?></label><?= $help('smtpsession.filter_ip') ?>
    <input type="text" id="ip" name="ip" class="form-control" value="<?= $e($filterIp) ?>" placeholder="198.51.100.7" />
  </div>
  <div class="align-self-end d-flex gap-2">
    <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-funnel me-1"></i><?= $te('maillog.filter') ?></button>
    <a href="/iredapd/smtp-sessions" class="btn btn-outline-secondary"><?= $te('maillog.clear') ?></a>
  </div>
</form>

<?php if (!$available): ?>
<div class="alert alert-info"><?= $te('smtpsession.unavailable') ?></div>
<?php endif; ?>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
      <thead>
        <tr>
          <th><?= $te('smtpsession.time') ?></th>
          <th><?= $te('common.action') ?></th>
          <th><?= $te('smtpsession.reason') ?></th>
          <th><?= $te('smtpsession.client') ?></th>
          <th><?= $te('greylist.sender') ?></th>
          <th><?= $te('greylist.recipient') ?></th>
          <th class="text-end"><?= $te('common.actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sessions as $row): ?>
        <?php $action = (string) ($row['action'] ?? ''); ?>
        <tr>
          <td class="text-nowrap text-body-secondary"><?= $e((string) ($row['time'] ?? '')) ?></td>
          <td><span class="badge badge-status <?= $tone('smtp_action', $action) ?>" title="<?= $e($actionHelp($action)) ?>"><?= $e($action) ?></span></td>
          <td><?= $e((string) ($row['reason'] ?? '')) ?></td>
          <td>
            <code><?= $e((string) ($row['client_address'] ?? '')) ?></code>
            <?php if (($row['reverse_client_name'] ?? '') !== ''): ?>
            <div class="small text-body-secondary"><?= $e((string) $row['reverse_client_name']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= $e((string) ($row['sender'] ?? '')) ?></td>
          <td><?= $e((string) ($row['recipient'] ?? '')) ?></td>
          <td class="text-end text-nowrap">
            <a href="/iredapd/smtp-sessions/<?= $e((string) $row['id']) ?>" class="btn btn-sm btn-outline-secondary" title="<?= $te('smtpsession.view') ?>"><i class="bi bi-eye"></i></a>
            <?php if (($row['sender'] ?? '') !== ''): ?>
            <form method="POST" action="/iredapd/smtp-sessions/<?= $e((string) $row['id']) ?>/wblist" class="d-inline">
              <?= $csrfField ?>
              <input type="hidden" name="target" value="sender" />
              <input type="hidden" name="wb" value="B" />
              <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="<?= $te('smtpsession.confirm_blacklist_sender') ?>" title="<?= $te('smtpsession.blacklist_sender') ?>"><i class="bi bi-person-slash"></i></button>
            </form>
            <?php endif; ?>
            <?php if (($row['reverse_client_name'] ?? '') !== ''): ?>
            <form method="POST" action="/iredapd/smtp-sessions/<?= $e((string) $row['id']) ?>/wblist" class="d-inline">
              <?= $csrfField ?>
              <input type="hidden" name="target" value="rdns" />
              <input type="hidden" name="wb" value="B" />
              <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="<?= $te('smtpsession.confirm_blacklist_rdns') ?>" title="<?= $te('smtpsession.blacklist_rdns') ?>"><i class="bi bi-hdd-network"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($sessions)): ?>
        <tr><td colspan="7" class="text-center text-body-secondary py-4"><?= $te('smtpsession.empty') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (isset($paginatedResult)): ?>
  <?php include __DIR__ . '/pagination.php'; ?>
<?php endif; ?>
