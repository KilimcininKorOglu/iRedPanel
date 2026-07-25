<?php $pageTitle = $t('mlist.create_title'); ?>
<div class="container">
  <div class="row">
    <div class="col-8">
      <h1><?= $te('mlist.create_title') ?></h1>

      <?php if (!empty($error)): ?>
      <div class="card bg-error text-white"><?= $e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="/mailing-lists/create">
        <?= $csrfField ?>

        <div class="row">
          <div class="col-6">
            <label for="localPart"><?= $te('alias.local_part') ?></label>
            <input type="text" id="localPart" name="localPart" required value="<?= $e($_POST['localPart'] ?? '') ?>" placeholder="list-name" />
          </div>
          <div class="col-6">
            <label for="domain"><?= $te('common.domain') ?></label>
            <select id="domain" name="domain" required>
              <?php foreach ($domains as $d): ?>
              <option value="<?= $e($d['domain'] ?? $d['name'] ?? '') ?>">@<?= $e($d['domain'] ?? $d['name'] ?? '') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <label for="name"><?= $te('alias.display_name') ?></label>
        <input type="text" id="name" name="name" value="<?= $e($_POST['name'] ?? '') ?>" placeholder="<?= $te('mlist.display_name_placeholder') ?>" />

        <label for="accessPolicy"><?= $te('alias.access_policy') ?></label>
        <select id="accessPolicy" name="accessPolicy">
          <option value="public"><?= $te('alias.policy_public') ?></option>
          <option value="domain"><?= $te('alias.policy_domain') ?></option>
          <option value="membersOnly"><?= $te('alias.policy_members') ?></option>
          <option value="moderatorsOnly"><?= $te('alias.policy_moderators') ?></option>
        </select>

        <div class="row">
          <div class="col-6">
            <label for="maxMsgSize"><?= $te('mlist.max_msg_size') ?></label>
            <input type="number" id="maxMsgSize" name="maxMsgSize" min="0" value="0" />
          </div>
          <div class="col-6">
            <label for="maxMembers"><?= $te('mlist.max_members') ?></label>
            <input type="number" id="maxMembers" name="maxMembers" min="0" value="0" />
          </div>
        </div>

        <button type="submit" class="button primary"><?= $te('mlist.create_button') ?></button>
        <a href="/mailing-lists" class="button outline"><?= $te('common.cancel') ?></a>
      </form>
    </div>
  </div>
</div>
