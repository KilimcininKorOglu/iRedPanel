<?php $pageTitle = $t('mlist.create_title'); ?>
<div class="page-header">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/mailing-lists"><?= $te('mlist.list_title') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $te('common.create') ?></li>
      </ol>
    </nav>
    <h1><?= $te('mlist.create_title') ?></h1>
  </div>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-xl-8">
    <form method="post" action="/mailing-lists/create" class="card">
      <?= $csrfField ?>
      <div class="card-body">
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label for="localPart" class="form-label"><?= $te('alias.local_part') ?></label>
            <input type="text" id="localPart" name="localPart" class="form-control" required value="<?= $e($_POST['localPart'] ?? '') ?>" placeholder="list-name" />
          </div>
          <div class="col-md-6">
            <label for="domain" class="form-label"><?= $te('common.domain') ?></label>
            <select id="domain" name="domain" class="form-select" required>
              <?php foreach ($domains as $d): ?>
              <option value="<?= $e($d['domainName']) ?>">@<?= $e($d['domainName']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="mb-3">
          <label for="name" class="form-label"><?= $te('alias.display_name') ?></label>
          <input type="text" id="name" name="name" class="form-control" value="<?= $e($_POST['name'] ?? '') ?>" placeholder="<?= $te('mlist.display_name_placeholder') ?>" />
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label for="accessPolicy" class="form-label"><?= $te('alias.access_policy') ?></label>
            <select id="accessPolicy" name="accessPolicy" class="form-select">
              <option value="public"><?= $te('alias.policy_public') ?></option>
              <option value="domain"><?= $te('alias.policy_domain') ?></option>
              <option value="membersOnly"><?= $te('alias.policy_members') ?></option>
              <option value="moderatorsOnly"><?= $te('alias.policy_moderators') ?></option>
            </select>
          </div>
          <div class="col-md-6">
            <label for="maxMsgSize" class="form-label"><?= $te('mlist.max_msg_size') ?></label>
            <input type="number" id="maxMsgSize" name="maxMsgSize" class="form-control" min="0" value="0" />
          </div>
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $te('mlist.create_button') ?></button>
        <a href="/mailing-lists" class="btn btn-outline-secondary"><?= $te('common.cancel') ?></a>
      </div>
    </form>
  </div>
</div>
