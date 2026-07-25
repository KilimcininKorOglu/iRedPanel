<?php $pageTitle = $e($domain->domainName); ?>
<div class="container">
  <div class="row">
    <div class="col-8">
      <h1><?= $e($domain->domainName) ?></h1>

      <div class="row breadcrumbs">
        <div class="col">
          <a href="/domains"><?= $te('domain.list_title') ?></a> /
          <span class="text-light"><?= $e($domain->domainName) ?></span>
        </div>
      </div>

      <?php if (!empty($error)): ?>
      <p class="text-error"><?= $e($error) ?></p>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
      <p class="text-success"><?= $e($success) ?></p>
      <?php endif; ?>

      <nav class="tabs">
        <a <?php if (($editMode ?? 'general') === 'general'): ?>class="active"<?php endif; ?>
           href="/domains/<?= $e($domain->domainName) ?>/edit"><?= $te('admin.tab_general') ?></a>
        <a <?php if (($editMode ?? '') === 'settings'): ?>class="active"<?php endif; ?>
           href="/domains/<?= $e($domain->domainName) ?>/settings"><?= $te('common.settings') ?></a>
        <a <?php if (($editMode ?? '') === 'catchall'): ?>class="active"<?php endif; ?>
           href="/domains/<?= $e($domain->domainName) ?>/catchall"><?= $te('domain.tab_catchall') ?></a>
        <a <?php if (($editMode ?? '') === 'bcc'): ?>class="active"<?php endif; ?>
           href="/domains/<?= $e($domain->domainName) ?>/bcc"><?= $te('domain.tab_bcc') ?></a>
        <a <?php if (($editMode ?? '') === 'relay'): ?>class="active"<?php endif; ?>
           href="/domains/<?= $e($domain->domainName) ?>/relay"><?= $te('domain.tab_relay') ?></a>
      </nav>

      <p class="text-light">
        <?= $te('domain.users') ?>: <?= $e($domain->currentUserCount) ?> |
        <?= $te('admin.created') ?>: <?= $e($domain->created ?? $t('common.na')) ?>
      </p>

      <?php if (($editMode ?? 'general') === 'general'): ?>
      <form method="post">
        <?= $csrfField ?>

        <p>
          <label for="description"><?= $te('common.description') ?></label>
          <input id="description" type="text" name="description"
            value="<?= $e($domain->description) ?>"
          />
        </p>

        <div class="row">
          <div class="col-6">
            <p>
              <label for="maxQuota"><?= $te('domain.max_quota') ?></label>
              <input id="maxQuota" type="number" name="maxQuota" min="0"
                value="<?= $e($domain->maxQuota) ?>"
              />
            </p>
          </div>
          <div class="col-6">
            <p>
              <label for="quota"><?= $te('domain.domain_quota') ?></label>
              <input id="quota" type="number" name="quota" min="0"
                value="<?= $e($domain->quota) ?>"
              />
            </p>
          </div>
        </div>

        <div class="row">
          <div class="col-6">
            <p>
              <label for="mailboxes"><?= $te('domain.max_mailboxes') ?></label>
              <input id="mailboxes" type="number" name="mailboxes" min="0"
                value="<?= $e($domain->mailboxes) ?>"
              />
            </p>
          </div>
          <div class="col-6">
            <p>
              <label for="aliases"><?= $te('domain.max_aliases') ?></label>
              <input id="aliases" type="number" name="aliases" min="0"
                value="<?= $e($domain->aliases) ?>"
              />
            </p>
          </div>
        </div>

        <p>
          <label for="transport"><?= $te('domain.transport') ?></label>
          <select id="transport" name="transport">
            <option value="dovecot" <?php if ($domain->transport === 'dovecot'): ?>selected<?php endif; ?>>dovecot</option>
            <option value="lmtp" <?php if ($domain->transport === 'lmtp'): ?>selected<?php endif; ?>>lmtp</option>
          </select>
        </p>

        <p>
          <label>
            <input type="checkbox" name="active" <?php if ($domain->active): ?>checked<?php endif; ?> />
            <?= $te('common.active') ?>
          </label>
        </p>

        <button type="submit" class="button primary"><?= $te('common.save_changes') ?></button>
        <a href="/domains" class="button outline"><?= $te('domain.back') ?></a>
      </form>

      <?php elseif (($editMode ?? '') === 'settings'): ?>
      <form method="post">
        <?= $csrfField ?>

        <p>
          <label for="defaultUserQuota"><?= $te('domain.default_user_quota') ?></label>
          <input id="defaultUserQuota" type="number" name="defaultUserQuota" min="0"
            value="<?= $e($domainSettings->defaultUserQuota ?? 0) ?>"
          />
        </p>

        <div class="row">
          <div class="col-6">
            <p>
              <label for="minPasswordLength"><?= $te('domain.min_password_length') ?></label>
              <input id="minPasswordLength" type="number" name="minPasswordLength" min="0"
                value="<?= $e($domainSettings->minPasswordLength ?? 0) ?>"
              />
            </p>
          </div>
          <div class="col-6">
            <p>
              <label for="maxPasswordLength"><?= $te('domain.max_password_length') ?></label>
              <input id="maxPasswordLength" type="number" name="maxPasswordLength" min="0"
                value="<?= $e($domainSettings->maxPasswordLength ?? 0) ?>"
              />
            </p>
          </div>
        </div>

        <p>
          <label for="disclaimer"><?= $te('domain.disclaimer_text') ?></label>
          <textarea id="disclaimer" name="disclaimer" rows="5"><?= $e($domainSettings->disclaimer ?? '') ?></textarea>
        </p>

        <button type="submit" class="button primary"><?= $te('mlist.save_settings') ?></button>
      </form>

      <?php elseif (($editMode ?? '') === 'catchall'): ?>
      <form method="post">
        <?= $csrfField ?>

        <fieldset>
          <legend><?= $te('domain.catchall_address') ?></legend>
          <p class="text-light"><?= $te('domain.catchall_desc') ?></p>

          <label for="catchallTarget"><?= $te('domain.forward_to') ?></label>
          <input id="catchallTarget" type="email" name="catchallTarget"
            value="<?= $e($catchallTarget ?? '') ?>"
            placeholder="<?= $te('domain.catchall_placeholder') ?>"
          />
          <p class="text-light"><?= $te('domain.catchall_remove_hint') ?></p>
        </fieldset>

        <button type="submit" class="button primary"><?= $te('domain.save_catchall') ?></button>
      </form>

      <?php elseif (($editMode ?? '') === 'bcc'): ?>
      <form method="post">
        <?= $csrfField ?>

        <fieldset>
          <legend><?= $te('domain.bcc_settings') ?></legend>
          <p class="text-light"><?= $te('domain.bcc_desc') ?></p>

          <label for="senderBcc"><?= $te('domain.sender_bcc') ?></label>
          <input id="senderBcc" type="email" name="senderBcc"
            value="<?= $e($senderBcc ?? '') ?>"
            placeholder="<?= $te('domain.sender_bcc_placeholder') ?>"
          />

          <label for="recipientBcc"><?= $te('domain.recipient_bcc') ?></label>
          <input id="recipientBcc" type="email" name="recipientBcc"
            value="<?= $e($recipientBcc ?? '') ?>"
            placeholder="<?= $te('domain.recipient_bcc_placeholder') ?>"
          />
        </fieldset>

        <button type="submit" class="button primary"><?= $te('domain.save_bcc') ?></button>
      </form>

      <?php elseif (($editMode ?? '') === 'relay'): ?>
      <form method="post">
        <?= $csrfField ?>

        <fieldset>
          <legend><?= $te('domain.relay_legend') ?></legend>
          <p class="text-light"><?= $te('domain.relay_desc') ?></p>

          <label for="relayhost"><?= $te('domain.relay_host') ?></label>
          <input id="relayhost" type="text" name="relayhost"
            value="<?= $e($domainRelayhost ?? '') ?>"
            placeholder="[smtp.relay.com]:587"
          />
          <p class="text-light"><?= $t('domain.relay_format') ?></p>
        </fieldset>

        <button type="submit" class="button primary"><?= $te('domain.save_relay') ?></button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
