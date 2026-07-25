<?php $pageTitle = $t('user.view_title'); ?>
<div class="container">
  <div class="row">
    <div class="col">
      <h1><?= $e($user->uid) ?></h1>

      <div class="row breadcrumbs">
        <div class="col">
          <a href="/domains"><?= $e($domain) ?></a> /
          <a href="/<?= $e($domain) ?>/users"><?= $te('domain.users') ?></a> /
          <span class="text-light"><?= $e($user->uid) ?></span>
        </div>
      </div>
      <div class="row">
        <div class="col">
          <nav class="tabs">
            <a
              <?php if ($editMode === 'general'): ?>class="active"<?php endif; ?>
              href="/<?= $e($domain) ?>/users/<?= $e($user->uid) ?>/general"
            ><?= $te('user.tab_general') ?></a>
            <a
              <?php if ($editMode === 'password'): ?>class="active"<?php endif; ?>
              href="/<?= $e($domain) ?>/users/<?= $e($user->uid) ?>/password"
            ><?= $te('common.password') ?></a>
            <a
              <?php if ($editMode === 'services'): ?>class="active"<?php endif; ?>
              href="/<?= $e($domain) ?>/users/<?= $e($user->uid) ?>/services"
            ><?= $te('user.tab_services') ?></a>
            <a
              <?php if ($editMode === 'forwarding'): ?>class="active"<?php endif; ?>
              href="/<?= $e($domain) ?>/users/<?= $e($user->uid) ?>/forwarding"
            ><?= $te('user.tab_forwarding') ?></a>
            <a
              <?php if ($editMode === 'aliases'): ?>class="active"<?php endif; ?>
              href="/<?= $e($domain) ?>/users/<?= $e($user->uid) ?>/aliases"
            ><?= $te('user.tab_aliases') ?></a>
            <a
              <?php if ($editMode === 'bcc'): ?>class="active"<?php endif; ?>
              href="/<?= $e($domain) ?>/users/<?= $e($user->uid) ?>/bcc"
            ><?= $te('domain.tab_bcc') ?></a>
            <a
              <?php if ($editMode === 'relay'): ?>class="active"<?php endif; ?>
              href="/<?= $e($domain) ?>/users/<?= $e($user->uid) ?>/relay"
            ><?= $te('domain.tab_relay') ?></a>
          </nav>
        </div>
      </div>

      <div class="row">
        <div class="col-8 col-6-md">
          <?php if (!empty($error)): ?>
          <p class="text-error"><?= $e($error) ?></p>
          <?php endif; ?>

          <?php if (!empty($success)): ?>
          <p class="text-success"><?= $e($success) ?></p>
          <?php endif; ?>

          <?php if ($editMode === 'general' && !empty($session['isGlobalAdmin'])): ?>
          <details style="margin-bottom:1rem;">
            <summary><?= $te('user.rename_email') ?></summary>
            <form method="post" action="/<?= $e($domain) ?>/users/<?= $e($user->uid) ?>/rename" style="margin-top:0.5rem;" data-confirm="<?= $te('user.rename_confirm') ?>">
              <?= $csrfField ?>
              <div class="row">
                <div class="col-6">
                  <input type="text" name="newUid" placeholder="<?= $te('user.new_username') ?>" required />
                </div>
                <div class="col-3">
                  <span>@<?= $e($domain) ?></span>
                </div>
                <div class="col-3">
                  <button type="submit" class="button outline"><?= $te('user.rename') ?></button>
                </div>
              </div>
            </form>
          </details>
          <?php endif; ?>

          <form method="post">
            <?= $csrfField ?>

            <?php if ($editMode === 'general'): ?>
            <input type="hidden" value="<?= $e($user->uid) ?>" name="uid" />

            <div class="row">
              <div class="col">
                <p>
                  <label for="accountStatus">
                    <input id="accountStatus" name="accountStatus"
                    type="checkbox" <?php if ($user->accountStatus): ?>checked<?php endif; ?>> <?= $te('user.record_active') ?>
                  </label>
                </p>
                <p>
                  <label for="mailQuota"><?= $te('user.quota_mb_label') ?></label>
                  <input
                    id="mailQuota"
                    name="mailQuota"
                    type="number"
                    value="<?= $e($user->mailQuota) ?>"
                    required
                  />
                </p>

                <p>
                  <label for="cn"><?= $te('user.full_name') ?></label>
                  <input id="cn" name="cn" type="text" value="<?= $e($user->cn) ?>" />
                </p>
              </div>
            </div>
            <div class="row">
              <div class="col">
                <p>
                  <label for="givenName"><?= $te('user.first_name') ?></label>
                  <input
                    id="givenName"
                    name="givenName"
                    type="text"
                    value="<?= $e($user->givenName) ?>"
                  />
                </p>
              </div>
              <div class="col">
                <p>
                  <label for="sn"><?= $te('user.last_name') ?></label>
                  <input id="sn" name="sn" type="text" value="<?= $e($user->sn) ?>" />
                </p>
              </div>
            </div>
            <div class="row">
              <div class="col">
                <p>
                  <label for="employeeNumber"><?= $te('user.employee_number') ?></label>
                  <input
                    id="employeeNumber"
                    name="employeeNumber"
                    type="text"
                    value="<?= $e($user->employeeNumber) ?>"
                  />
                </p>
                <p>
                  <label for="title"><?= $te('user.position') ?></label>
                  <input
                    id="title"
                    name="title"
                    type="text"
                    value="<?= $e($user->title) ?>"
                  />
                </p>
                <p>
                  <label for="mobile"><?= $te('user.mobile_phone') ?></label>
                  <input
                    id="mobile"
                    name="mobile"
                    type="text"
                    value="<?= $e($user->mobile) ?>"
                  />
                </p>
                <p>
                  <label for="telephoneNumber"><?= $te('user.work_phone') ?></label>
                  <input
                    id="telephoneNumber"
                    name="telephoneNumber"
                    type="text"
                    value="<?= $e($user->telephoneNumber) ?>"
                  />
                </p>
                <p>
                  <label for="domainGlobalAdmin">
                    <input id="domainGlobalAdmin" name="domainGlobalAdmin"
                    type="checkbox" <?php if ($user->domainGlobalAdmin): ?>checked<?php endif; ?>> <?= $te('admin.global_administrator') ?>
                  </label>
                </p>
                <p>
                  <button type="submit" class="button primary">
                    <?= $te('common.save') ?>
                  </button>
                </p>
              </div>
            </div>

            <?php elseif ($editMode === 'password'): ?>
            <?php if (!empty($requireOldPassword)): ?>
            <p>
              <label for="old_password"><?= $te('user.current_password') ?></label>
              <input name="old_password" type="password" id="old_password" required
                <?php if (!empty($validationErrors['old_password'])): ?>class="error"<?php endif; ?>
              />
              <?php if (!empty($validationErrors['old_password'])): ?>
              <p class="text-error"><?= $e($validationErrors['old_password']) ?></p>
              <?php endif; ?>
            </p>
            <?php endif; ?>
            <p>
              <label for="password"><?= $te('common.password') ?></label>
              <input name="password" type="password" id="password" required autocomplete="new-password"
                <?php if (!empty($validationErrors['password'])): ?>class="error"<?php endif; ?>
              />
              <?php if (!empty($validationErrors['password'])): ?>
              <p class="text-error"><?= $e($validationErrors['password']) ?></p>
              <?php endif; ?>
            </p>
            <p>
              <label for="password_repeat"><?= $te('user.password_repeat') ?></label>
              <input name="password_repeat" type="password" id="password_repeat" required
                <?php if (!empty($validationErrors['password_repeat'])): ?>class="error"<?php endif; ?>
              />
              <?php if (!empty($validationErrors['password_repeat'])): ?>
              <p class="text-error"><?= $e($validationErrors['password_repeat']) ?></p>
              <?php endif; ?>
            </p>
            <p>
              <button type="button" class="button outline" onclick="generatePassword()"><?= $te('user.generate_password') ?></button>
            </p>
            <p>
              <button type="submit" class="button primary">
                <?= $te('common.save') ?>
              </button>
            </p>

            <?php elseif ($editMode === 'services'): ?>
            <h3><?= $te('user.mail_services') ?></h3>
            <p>
              <label><input type="checkbox" name="enableSmtp" <?php if ($user->enableSmtp): ?>checked<?php endif; ?> /> SMTP</label>
            </p>
            <p>
              <label><input type="checkbox" name="enableSmtpSecured" <?php if ($user->enableSmtpSecured): ?>checked<?php endif; ?> /> SMTP (TLS)</label>
            </p>
            <p>
              <label><input type="checkbox" name="enablePop3" <?php if ($user->enablePop3): ?>checked<?php endif; ?> /> POP3</label>
            </p>
            <p>
              <label><input type="checkbox" name="enablePop3Secured" <?php if ($user->enablePop3Secured): ?>checked<?php endif; ?> /> POP3 (TLS)</label>
            </p>
            <p>
              <label><input type="checkbox" name="enableImap" <?php if ($user->enableImap): ?>checked<?php endif; ?> /> IMAP</label>
            </p>
            <p>
              <label><input type="checkbox" name="enableImapSecured" <?php if ($user->enableImapSecured): ?>checked<?php endif; ?> /> IMAP (TLS)</label>
            </p>
            <p>
              <label><input type="checkbox" name="enableManagesieve" <?php if ($user->enableManagesieve): ?>checked<?php endif; ?> /> ManageSieve</label>
            </p>
            <p>
              <label><input type="checkbox" name="enableManagesieveSecured" <?php if ($user->enableManagesieveSecured): ?>checked<?php endif; ?> /> ManageSieve (TLS)</label>
            </p>
            <p>
              <label><input type="checkbox" name="enableSogo" <?php if ($user->enableSogo): ?>checked<?php endif; ?> /> SOGo Webmail</label>
            </p>
            <p>
              <button type="submit" class="button primary"><?= $te('user.save_services') ?></button>
            </p>

            <?php elseif ($editMode === 'forwarding'): ?>
            <h3><?= $te('user.email_forwarding') ?></h3>
            <p>
              <label for="forwardingAddresses"><?= $te('user.forwarding_addresses') ?></label>
              <textarea id="forwardingAddresses" name="forwardingAddresses" rows="5" placeholder="user@example.com"><?= $e(implode("\n", $forwardings ?? [])) ?></textarea>
            </p>
            <p>
              <label>
                <input type="checkbox" name="keepCopy" <?php if ($keepCopy ?? true): ?>checked<?php endif; ?> />
                <?= $te('user.keep_copy') ?>
              </label>
            </p>
            <p>
              <button type="submit" class="button primary"><?= $te('user.save_forwarding') ?></button>
            </p>
            <?php endif; ?>
          </form>

          <?php if ($editMode === 'aliases'): ?>
          <h3><?= $te('user.per_user_aliases') ?></h3>
          <p class="text-light"><?= $te('user.aliases_desc') ?></p>

          <form method="post">
            <?= $csrfField ?>
            <input type="hidden" name="action" value="add" />
            <div class="row">
              <div class="col-8">
                <input type="email" name="newAlias" placeholder="alias@example.com" required />
              </div>
              <div class="col-4">
                <button type="submit" class="button primary outline"><?= $te('user.add_alias') ?></button>
              </div>
            </div>
          </form>

          <?php if (!empty($userAliases)): ?>
          <table class="striped" style="margin-top:1rem;">
            <thead>
              <tr>
                <th><?= $te('user.alias_address') ?></th>
                <th><?= $te('common.actions') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($userAliases as $aliasAddr): ?>
              <tr>
                <td><?= $e($aliasAddr) ?></td>
                <td>
                  <form method="post" style="display:inline">
                    <?= $csrfField ?>
                    <input type="hidden" name="action" value="remove" />
                    <input type="hidden" name="aliasAddress" value="<?= $e($aliasAddr) ?>" />
                    <button type="submit" class="button error outline" data-confirm="<?= $e($t('user.alias_remove_confirm', ['alias' => $aliasAddr])) ?>"><?= $te('wblist.remove') ?></button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php else: ?>
          <p class="text-light" style="margin-top:1rem;"><?= $te('user.no_aliases') ?></p>
          <?php endif; ?>
          <?php endif; ?>

          <?php if ($editMode === 'bcc'): ?>
          <form method="post">
            <?= $csrfField ?>
            <h3><?= $te('domain.bcc_settings') ?></h3>
            <p class="text-light"><?= $te('user.bcc_desc') ?></p>

            <label for="senderBcc"><?= $te('domain.sender_bcc') ?></label>
            <input id="senderBcc" type="email" name="senderBcc"
              value="<?= $e($userSenderBcc ?? '') ?>"
              placeholder="<?= $te('domain.sender_bcc_placeholder') ?>"
            />

            <label for="recipientBcc"><?= $te('domain.recipient_bcc') ?></label>
            <input id="recipientBcc" type="email" name="recipientBcc"
              value="<?= $e($userRecipientBcc ?? '') ?>"
              placeholder="<?= $te('domain.recipient_bcc_placeholder') ?>"
            />

            <p><button type="submit" class="button primary"><?= $te('domain.save_bcc') ?></button></p>
          </form>
          <?php endif; ?>

          <?php if ($editMode === 'relay'): ?>
          <form method="post">
            <?= $csrfField ?>
            <h3><?= $te('domain.relay_legend') ?></h3>
            <p class="text-light"><?= $te('user.relay_desc') ?></p>

            <label for="relayhost"><?= $te('domain.relay_host') ?></label>
            <input id="relayhost" type="text" name="relayhost"
              value="<?= $e($userRelayhost ?? '') ?>"
              placeholder="[smtp.relay.com]:587"
            />
            <p class="text-light"><?= $t('domain.relay_format') ?></p>

            <p><button type="submit" class="button primary"><?= $te('domain.save_relay') ?></button></p>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
