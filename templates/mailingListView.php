<?php $pageTitle = $t('mlist.view_title', ['address' => $ml->address ?? '']); ?>
<div class="container">
  <div class="row">
    <div class="col-8">
      <h1><?= $te('mlist.view_title', ['address' => $ml->address]) ?></h1>

      <?php if (!empty($success)): ?>
      <div class="card bg-success text-white"><?= $e($success) ?></div>
      <?php endif; ?>
      <?php if (!empty($error)): ?>
      <div class="card bg-error text-white"><?= $e($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <?= $csrfField ?>
        <input type="hidden" name="action" value="updateSettings" />

        <fieldset>
          <legend><?= $te('mlist.list_settings') ?></legend>

          <label for="name"><?= $te('alias.display_name') ?></label>
          <input type="text" id="name" name="name" value="<?= $e($ml->name) ?>" />

          <label for="accessPolicy"><?= $te('alias.access_policy') ?></label>
          <select id="accessPolicy" name="accessPolicy">
            <option value="public" <?= $ml->accessPolicy === 'public' ? 'selected' : '' ?>><?= $te('mlist.policy_public_short') ?></option>
            <option value="domain" <?= $ml->accessPolicy === 'domain' ? 'selected' : '' ?>><?= $te('common.domain') ?></option>
            <option value="membersOnly" <?= $ml->accessPolicy === 'membersOnly' ? 'selected' : '' ?>><?= $te('alias.policy_members') ?></option>
            <option value="moderatorsOnly" <?= $ml->accessPolicy === 'moderatorsOnly' ? 'selected' : '' ?>><?= $te('alias.policy_moderators') ?></option>
          </select>

          <label for="maxMsgSize"><?= $te('mlist.max_msg_size') ?></label>
          <input type="number" id="maxMsgSize" name="maxMsgSize" min="0" value="<?= $e($ml->maxMsgSize) ?>" />

          <label>
            <input type="checkbox" name="active" <?= $ml->active ? 'checked' : '' ?> />
            <?= $te('common.active') ?>
          </label>

          <?php if ($supportsNewsletter): ?>
          <label>
            <input type="checkbox" name="isNewsletter" <?= $ml->isNewsletter ? 'checked' : '' ?> />
            <?= $te('mlist.newsletter') ?>
          </label>
          <p class="text-light"><?= $te('mlist.newsletter_hint') ?></p>
          <?php if ($ml->isNewsletter && $ml->mlid !== ''): ?>
          <p>
            <?= $te('mlist.subscribe_page') ?>: <a href="<?= $e($newsletterBaseUrl . '/subscribe/' . $ml->mlid) ?>"><?= $e($newsletterBaseUrl . '/subscribe/' . $ml->mlid) ?></a><br />
            <?= $te('mlist.unsubscribe_page') ?>: <a href="<?= $e($newsletterBaseUrl . '/unsubscribe/' . $ml->mlid) ?>"><?= $e($newsletterBaseUrl . '/unsubscribe/' . $ml->mlid) ?></a>
          </p>
          <?php endif; ?>
          <?php endif; ?>

          <p class="text-light"><?= $te('mlist.transport') ?>: <?= $e($ml->transport) ?></p>
        </fieldset>

        <button type="submit" class="button primary"><?= $te('mlist.save_settings') ?></button>
      </form>

      <hr />

      <form method="post">
        <?= $csrfField ?>
        <input type="hidden" name="action" value="updateOwners" />

        <fieldset>
          <legend><?= $te('mlist.list_owners') ?></legend>
          <label for="owners"><?= $te('mlist.owners_hint') ?></label>
          <textarea id="owners" name="owners" rows="4"><?= $e(implode("\n", $owners)) ?></textarea>
          <p class="text-light"><?= $te('mlist.owners_desc') ?></p>
        </fieldset>

        <button type="submit" class="button primary outline"><?= $te('mlist.save_owners') ?></button>
      </form>

      <hr />

      <?php if ($moderatorsError !== null): ?>
      <p class="text-error"><?= $e($moderatorsError) ?></p>
      <?php else: ?>
      <form method="post">
        <?= $csrfField ?>
        <input type="hidden" name="action" value="updateModerators" />

        <fieldset>
          <legend><?= $te('mlist.list_moderators') ?></legend>
          <label for="moderators"><?= $te('mlist.moderators_hint') ?></label>
          <textarea id="moderators" name="moderators" rows="4"><?= $e(implode("\n", $moderators)) ?></textarea>
          <p class="text-light"><?= $te('mlist.moderators_desc') ?></p>
        </fieldset>

        <button type="submit" class="button primary outline"><?= $te('mlist.save_moderators') ?></button>
      </form>
      <?php endif; ?>

      <hr />

      <h3 id="subscribers"><?= $te('mlist.subscribers') ?> (<?= count($subscribers) ?>)</h3>
      <?php if ($subscribersError !== null): ?>
      <p class="text-error"><?= $e($subscribersError) ?></p>
      <?php elseif ($subscribers === []): ?>
      <p class="text-light"><?= $te('mlist.no_subscribers') ?></p>
      <?php else: ?>
      <table class="striped">
        <thead>
          <tr><th><?= $te('common.email') ?></th><th><?= $te('common.actions') ?></th></tr>
        </thead>
        <tbody>
          <?php foreach ($subscribers as $subscriber): ?>
          <tr>
            <td><?= $e($subscriber) ?></td>
            <td>
              <form method="post" style="margin:0" data-confirm="<?= $te('mlist.remove_subscriber_confirm', ['address' => $subscriber]) ?>">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="removeSubscriber" />
                <input type="hidden" name="subscriber" value="<?= $e($subscriber) ?>" />
                <button type="submit" class="button outline error"><?= $te('common.remove') ?></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>

      <form method="post">
        <?= $csrfField ?>
        <input type="hidden" name="action" value="addSubscribers" />

        <fieldset>
          <legend><?= $te('mlist.add_subscribers') ?></legend>
          <label for="subscribersInput"><?= $te('mlist.subscribers_hint') ?></label>
          <textarea id="subscribersInput" name="subscribers" rows="4" required><?= $e($subscribersDraft) ?></textarea>
        </fieldset>

        <button type="submit" class="button primary outline"><?= $te('mlist.add_subscribers') ?></button>
      </form>

      <hr />

      <?php if (!empty($session['isGlobalAdmin'])): ?>
      <form method="post" action="/mailing-lists/<?= $e($ml->address) ?>/delete" data-confirm="<?= $te('mlist.delete_confirm', ['address' => $ml->address]) ?>">
        <?= $csrfField ?>
        <button type="submit" class="button error"><?= $te('mlist.delete_button') ?></button>
      </form>
      <?php endif; ?>

      <p><a href="/mailing-lists">&larr; <?= $te('mlist.back') ?></a></p>
    </div>
  </div>
</div>
