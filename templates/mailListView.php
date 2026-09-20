<?php $pageTitle = $t('maillist.view_title') . ': ' . $mailList->address; ?>
<div class="page-header">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/mail-lists"><?= $te('maillist.list_title') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $e($mailList->address) ?></li>
      </ol>
    </nav>
    <h1><?= $e($mailList->address) ?></h1>
  </div>
  <div class="page-actions">
    <form method="post" action="/mail-lists/<?= $e($mailList->address) ?>/delete" data-confirm="<?= $te('maillist.delete_confirm', ['address' => $mailList->address]) ?>">
      <?= $csrfField ?>
      <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash3 me-1"></i><?= $te('common.delete') ?></button>
    </form>
  </div>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
<div class="alert alert-success"><?= $e($success) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-xl-8">
    <form method="post">
      <?= $csrfField ?>

      <div class="card">
        <div class="card-header"><?= $te('common.settings') ?></div>
        <div class="card-body">
          <div class="mb-3">
            <label for="name" class="form-label"><?= $te('alias.display_name') ?></label><?= $help('alias.display_name') ?>
            <input type="text" id="name" name="name" class="form-control" value="<?= $e($mailList->name) ?>" />
          </div>

          <div class="mb-3">
            <label for="accessPolicy" class="form-label"><?= $te('alias.access_policy') ?></label><?= $help('alias.access_policy') ?>
            <?php $accessPolicy = $mailList->accessPolicy; ?>
            <?php include __DIR__ . '/mailListPolicyOptions.php'; ?>
          </div>

          <div class="mb-3">
            <label for="maxMessageSize" class="form-label"><?= $te('throttle.max_message_size') ?></label><?= $help('maillist.max_message_size', [], $t('throttle.max_message_size')) ?>
            <input type="number" id="maxMessageSize" name="maxMessageSize" min="0" class="form-control" value="<?= $e((string) $mailList->maxMessageSize) ?>" />
          </div>

          <div class="form-check form-switch">
            <input type="checkbox" id="active" name="active" value="1" class="form-check-input"<?= $mailList->active ? ' checked' : '' ?> />
            <label for="active" class="form-check-label"><?= $te('common.active') ?></label><?= $help('common.active') ?>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><?= $te('alias.members') ?></div>
        <div class="card-body">
          <label for="members" class="form-label"><?= $te('alias.members_hint') ?></label><?= $help('alias.members_hint') ?>
          <textarea id="members" name="members" data-account-picker="multi" rows="6" class="form-control"><?= $e(implode("\n", $mailList->members)) ?></textarea>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><?= $te('maillist.senders') ?></div>
        <div class="card-body">
          <div class="mb-3">
            <label for="moderators" class="form-label"><?= $te('alias.moderators_hint') ?></label><?= $help('alias.moderators_hint') ?>
            <textarea id="moderators" name="moderators" data-account-picker="multi" rows="3" class="form-control"><?= $e(implode("\n", $mailList->moderators)) ?></textarea>
          </div>
          <div>
            <label for="allowedSenders" class="form-label"><?= $te('maillist.allowed_hint') ?></label><?= $help('maillist.allowed_hint') ?>
            <textarea id="allowedSenders" name="allowedSenders" data-account-picker="multi" rows="3" class="form-control"><?= $e(implode("\n", $mailList->allowedSenders)) ?></textarea>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $te('common.save') ?></button>
        <a href="/mail-lists" class="btn btn-outline-secondary"><?= $te('common.cancel') ?></a>
      </div>
    </form>
  </div>
</div>
