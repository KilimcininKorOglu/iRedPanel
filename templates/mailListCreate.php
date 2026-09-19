<?php $pageTitle = $t('maillist.create_title'); ?>
<div class="page-header">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/mail-lists"><?= $te('maillist.list_title') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $te('common.create') ?></li>
      </ol>
    </nav>
    <h1><?= $te('maillist.create_title') ?></h1>
  </div>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-xl-8">
    <form method="post" action="/mail-lists/create">
      <?= $csrfField ?>

      <div class="card">
        <div class="card-header"><?= $te('common.address') ?></div>
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
                <option value="<?= $e($d['domainName']) ?>"<?= (($_POST['domain'] ?? '') === $d['domainName']) ? ' selected' : '' ?>>@<?= $e($d['domainName']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label for="name" class="form-label"><?= $te('alias.display_name') ?></label>
            <input type="text" id="name" name="name" class="form-control" value="<?= $e($_POST['name'] ?? '') ?>" placeholder="<?= $te('alias.display_name_placeholder') ?>" />
          </div>

          <div>
            <label for="accessPolicy" class="form-label"><?= $te('alias.access_policy') ?></label>
            <?php $accessPolicy = $_POST['accessPolicy'] ?? 'public'; ?>
            <?php include __DIR__ . '/mailListPolicyOptions.php'; ?>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><?= $te('alias.members') ?></div>
        <div class="card-body">
          <label for="members" class="form-label"><?= $te('alias.members_hint') ?></label>
          <textarea id="members" name="members" data-account-picker="multi" rows="6" class="form-control" placeholder="user1@example.com&#10;user2@example.com"><?= $e($_POST['members'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $te('common.create') ?></button>
        <a href="/mail-lists" class="btn btn-outline-secondary"><?= $te('common.cancel') ?></a>
      </div>
    </form>
  </div>
</div>
