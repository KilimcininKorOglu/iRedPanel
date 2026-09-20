<?php
$pageTitle = $t('smtpsession.view_title');
// Every column of the row, in the order the page shows them. The key is the
// column and the value is its label key.
// $session is the login session in every template, so the row uses its own name.
// The domain columns are left out, because the address columns already carry the
// domain, and the server and encryption columns are joined into one row each.
$fields = [
    'time' => 'smtpsession.time',
    'action' => 'common.action',
    'reason' => 'smtpsession.reason',
    'client_address' => 'greylist.client_ip',
    'reverse_client_name' => 'smtpsession.reverse_client_name',
    'helo_name' => 'smtpsession.helo_name',
    'sender' => 'greylist.sender',
    'sasl_username' => 'smtpsession.sasl_username',
    'recipient' => 'greylist.recipient',
    'encryption' => 'smtpsession.encryption',
    'server' => 'smtpsession.server',
    'instance' => 'smtpsession.instance',
];
$action = (string) ($smtpSession['action'] ?? '');
$smtpSession['encryption'] = trim(($smtpSession['encryption_protocol'] ?? '') . ' ' . ($smtpSession['encryption_cipher'] ?? ''));
$smtpSession['server'] = trim(($smtpSession['server_address'] ?? '') . ' ' . ($smtpSession['server_port'] ?? ''));
?>
<div class="page-header">
  <div>
    <h1><?= $te('smtpsession.view_title') ?></h1>
    <div class="small text-body-secondary mt-1">#<?= $e((string) $smtpSession['id']) ?></div>
  </div>
  <div class="page-actions">
    <a href="/iredapd/smtp-sessions" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i><?= $te('common.back') ?></a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header"><?= $te('smtpsession.view_title') ?></div>
  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0">
      <tbody>
        <?php foreach ($fields as $column => $label): ?>
        <?php if ((string) ($smtpSession[$column] ?? '') !== ''): ?>
        <tr>
          <th class="text-nowrap w-25"><?= $te($label) ?></th>
          <td class="text-break">
            <?php if ($column === 'action'): ?>
            <span class="badge badge-status <?= $tone('smtp_action', $action) ?>" title="<?= $e($actionHelp($action)) ?>"><?= $e($action) ?></span>
            <?php if ($actionHelp($action) !== ''): ?>
            <div class="small text-body-secondary mt-1"><?= $e($actionHelp($action)) ?></div>
            <?php endif; ?>
            <?php else: ?>
            <?= $e((string) $smtpSession[$column]) ?>
            <?php endif; ?>
          </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (($smtpSession['sender'] ?? '') !== '' || ($smtpSession['reverse_client_name'] ?? '') !== ''): ?>
<div class="card">
  <div class="card-header"><?= $te('smtpsession.wblist_title') ?></div>
  <div class="card-body d-flex flex-wrap gap-2">
    <?php foreach (['W' => 'btn-outline-success', 'B' => 'btn-outline-danger'] as $wb => $class): ?>
    <?php if (($smtpSession['sender'] ?? '') !== ''): ?>
    <form method="POST" action="/iredapd/smtp-sessions/<?= $e((string) $smtpSession['id']) ?>/wblist">
      <?= $csrfField ?>
      <input type="hidden" name="target" value="sender" />
      <input type="hidden" name="wb" value="<?= $e($wb) ?>" />
      <button type="submit" class="btn <?= $e($class) ?>" data-confirm="<?= $te($wb === 'W' ? 'smtpsession.confirm_whitelist_sender' : 'smtpsession.confirm_blacklist_sender') ?>">
        <?= $te($wb === 'W' ? 'smtpsession.whitelist_sender' : 'smtpsession.blacklist_sender') ?>
      </button>
    </form>
    <?php endif; ?>
    <?php if (($smtpSession['reverse_client_name'] ?? '') !== ''): ?>
    <form method="POST" action="/iredapd/smtp-sessions/<?= $e((string) $smtpSession['id']) ?>/wblist">
      <?= $csrfField ?>
      <input type="hidden" name="target" value="rdns" />
      <input type="hidden" name="wb" value="<?= $e($wb) ?>" />
      <button type="submit" class="btn <?= $e($class) ?>" data-confirm="<?= $te($wb === 'W' ? 'smtpsession.confirm_whitelist_rdns' : 'smtpsession.confirm_blacklist_rdns') ?>">
        <?= $te($wb === 'W' ? 'smtpsession.whitelist_rdns' : 'smtpsession.blacklist_rdns') ?>
      </button>
    </form>
    <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
