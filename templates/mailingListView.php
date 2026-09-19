<?php $pageTitle = $t('mlist.view_title', ['address' => $ml->address ?? '']); ?>
<div class="page-header">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/mailing-lists"><?= $te('mlist.list_title') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $e($ml->address) ?></li>
      </ol>
    </nav>
    <h1><?= $te('mlist.view_title', ['address' => $ml->address]) ?></h1>
  </div>
  <?php if (!empty($session['isGlobalAdmin'])): ?>
  <div class="page-actions">
    <form method="post" action="/mailing-lists/<?= $e($ml->address) ?>/delete" data-confirm="<?= $te('mlist.delete_confirm', ['address' => $ml->address]) ?>">
      <?= $csrfField ?>
      <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash3 me-1"></i><?= $te('mlist.delete_button') ?></button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php if (!empty($success)): ?>
<div class="alert alert-success"><?= $e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-xl-8">
    <form method="post" class="card">
      <?= $csrfField ?>
      <input type="hidden" name="action" value="updateSettings" />
      <div class="card-header"><?= $te('mlist.list_settings') ?></div>
      <div class="card-body">
        <div class="mb-3">
          <label for="name" class="form-label"><?= $te('alias.display_name') ?></label>
          <input type="text" id="name" name="name" class="form-control" value="<?= $e($ml->name) ?>" />
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label for="accessPolicy" class="form-label"><?= $te('alias.access_policy') ?></label>
            <select id="accessPolicy" name="accessPolicy" class="form-select">
              <option value="public"<?= $ml->accessPolicy === 'public' ? ' selected' : '' ?>><?= $te('mlist.policy_public_short') ?></option>
              <option value="domain"<?= $ml->accessPolicy === 'domain' ? ' selected' : '' ?>><?= $te('common.domain') ?></option>
              <option value="membersOnly"<?= $ml->accessPolicy === 'membersOnly' ? ' selected' : '' ?>><?= $te('alias.policy_members') ?></option>
              <option value="moderatorsOnly"<?= $ml->accessPolicy === 'moderatorsOnly' ? ' selected' : '' ?>><?= $te('alias.policy_moderators') ?></option>
            </select>
          </div>
          <div class="col-md-6">
            <label for="maxMsgSize" class="form-label"><?= $te('mlist.max_msg_size') ?></label>
            <input type="number" id="maxMsgSize" name="maxMsgSize" class="form-control" min="0" value="<?= $e($ml->maxMsgSize) ?>" />
          </div>
        </div>

        <div class="form-check form-switch mb-2">
          <input type="checkbox" class="form-check-input" id="active" name="active" <?= $ml->active ? 'checked' : '' ?> />
          <label class="form-check-label" for="active"><?= $te('common.active') ?></label>
        </div>

        <?php if ($supportsNewsletter): ?>
        <div class="form-check form-switch">
          <input type="checkbox" class="form-check-input" id="isNewsletter" name="isNewsletter" <?= $ml->isNewsletter ? 'checked' : '' ?> />
          <label class="form-check-label" for="isNewsletter"><?= $te('mlist.newsletter') ?></label>
        </div>
        <div class="form-text"><?= $te('mlist.newsletter_hint') ?></div>
        <?php if ($ml->isNewsletter && $ml->mlid !== ''): ?>
        <dl class="row small mt-3 mb-0">
          <dt class="col-sm-4 text-body-secondary fw-normal"><?= $te('mlist.subscribe_page') ?></dt>
          <dd class="col-sm-8 text-break"><a href="<?= $e($newsletterBaseUrl . '/subscribe/' . $ml->mlid) ?>"><?= $e($newsletterBaseUrl . '/subscribe/' . $ml->mlid) ?></a></dd>
          <dt class="col-sm-4 text-body-secondary fw-normal"><?= $te('mlist.unsubscribe_page') ?></dt>
          <dd class="col-sm-8 text-break mb-0"><a href="<?= $e($newsletterBaseUrl . '/unsubscribe/' . $ml->mlid) ?>"><?= $e($newsletterBaseUrl . '/unsubscribe/' . $ml->mlid) ?></a></dd>
        </dl>
        <?php endif; ?>
        <?php endif; ?>

        <div class="small text-body-secondary mt-3"><?= $te('mlist.transport') ?>: <code><?= $e($ml->transport) ?></code></div>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('mlist.save_settings') ?></button>
      </div>
    </form>

    <form method="post" class="card">
      <?= $csrfField ?>
      <input type="hidden" name="action" value="updateOwners" />
      <div class="card-header"><?= $te('mlist.list_owners') ?></div>
      <div class="card-body">
        <label for="owners" class="form-label"><?= $te('mlist.owners_hint') ?></label>
        <textarea id="owners" name="owners" data-account-picker="multi" rows="4" class="form-control"><?= $e(implode("\n", $owners)) ?></textarea>
        <div class="form-text"><?= $te('mlist.owners_desc') ?></div>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('mlist.save_owners') ?></button>
      </div>
    </form>

    <?php if ($moderatorsError !== null): ?>
    <div class="alert alert-danger"><?= $e($moderatorsError) ?></div>
    <?php else: ?>
    <form method="post" class="card">
      <?= $csrfField ?>
      <input type="hidden" name="action" value="updateModerators" />
      <div class="card-header"><?= $te('mlist.list_moderators') ?></div>
      <div class="card-body">
        <label for="moderators" class="form-label"><?= $te('mlist.moderators_hint') ?></label>
        <textarea id="moderators" name="moderators" data-account-picker="multi" rows="4" class="form-control"><?= $e(implode("\n", $moderators)) ?></textarea>
        <div class="form-text"><?= $te('mlist.moderators_desc') ?></div>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('mlist.save_moderators') ?></button>
      </div>
    </form>
    <?php endif; ?>

    <div class="card" id="subscribers">
      <div class="card-header d-flex align-items-center gap-2">
        <?= $te('mlist.subscribers') ?> <span class="badge <?= $tone('count', 'subscribers') ?>"><?= count($subscribers) ?></span>
      </div>
      <?php if ($subscribersError !== null): ?>
      <div class="card-body"><div class="alert alert-danger mb-0"><?= $e($subscribersError) ?></div></div>
      <?php elseif ($subscribers === []): ?>
      <div class="card-body text-body-secondary"><?= $te('mlist.no_subscribers') ?></div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr><th><?= $te('common.email') ?></th><th class="text-end"><?= $te('common.actions') ?></th></tr>
          </thead>
          <tbody>
            <?php foreach ($subscribers as $subscriber): ?>
            <tr>
              <td><?= $e($subscriber) ?></td>
              <td>
                <form method="post" class="table-actions" data-confirm="<?= $te('mlist.remove_subscriber_confirm', ['address' => $subscriber]) ?>">
                  <?= $csrfField ?>
                  <input type="hidden" name="action" value="removeSubscriber" />
                  <input type="hidden" name="subscriber" value="<?= $e($subscriber) ?>" />
                  <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg me-1"></i><?= $te('common.remove') ?></button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <form method="post" class="card">
      <?= $csrfField ?>
      <input type="hidden" name="action" value="addSubscribers" />
      <div class="card-header"><?= $te('mlist.add_subscribers') ?></div>
      <div class="card-body">
        <label for="subscribersInput" class="form-label"><?= $te('mlist.subscribers_hint') ?></label>
        <textarea id="subscribersInput" name="subscribers" data-account-picker="multi" rows="4" class="form-control" required><?= $e($subscribersDraft) ?></textarea>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('mlist.add_subscribers') ?></button>
      </div>
    </form>

    <a href="/mailing-lists"><i class="bi bi-arrow-left me-1"></i><?= $te('mlist.back') ?></a>
  </div>
</div>
