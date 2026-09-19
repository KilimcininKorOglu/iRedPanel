<?php $pageTitle = $t('alias.view_title', ['address' => $alias->address ?? '']); ?>
<div class="page-header">
  <div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/aliases"><?= $te('alias.list_title') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= $e($alias->address) ?></li>
      </ol>
    </nav>
    <h1><?= $te('alias.view_title', ['address' => $alias->address]) ?>
      <?php if ($managed): ?>
      <span class="badge badge-status fs-6 align-middle <?= $tone('managed', 'directory') ?>" title="<?= $te('resource.managed_help') ?>"><i class="bi bi-diagram-3 me-1"></i><?= $te('resource.managed_by') ?></span>
      <?php endif; ?>
    </h1>
  </div>
  <?php if (!empty($session['isGlobalAdmin'])): ?>
  <div class="page-actions">
    <form method="post" action="/aliases/<?= $e($alias->address) ?>/delete" data-confirm="<?= $te('alias.delete_confirm_perm', ['address' => $alias->address]) ?>">
      <?= $csrfField ?>
      <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash3 me-1"></i><?= $te('alias.delete_button') ?></button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php if ($managed): ?>
<div class="alert alert-info"><?= $te('resource.managed_group_help') ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
<div class="alert alert-success"><?= $e($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>

<ul class="nav nav-pills mb-4">
  <li class="nav-item"><a class="nav-link" href="#section-settings"><?= $te('common.settings') ?></a></li>
  <li class="nav-item"><a class="nav-link" href="#section-members"><?= $te('alias.members') ?> <span class="badge <?= $tone('count', 'members') ?>"><?= count($members) ?></span></a></li>
  <li class="nav-item"><a class="nav-link" href="#section-moderators"><?= $te('alias.moderators') ?> <span class="badge <?= $tone('count', 'moderators') ?>"><?= count($moderators) ?></span></a></li>
</ul>

<div class="row">
  <div class="col-xl-8">
    <form method="post" action="/aliases/<?= $e($alias->address) ?>" class="card" id="section-settings">
      <?= $csrfField ?>
      <input type="hidden" name="action" value="updateSettings" />
      <div class="card-header"><?= $te('alias.alias_settings') ?></div>
      <div class="card-body">
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label for="name" class="form-label"><?= $te('alias.display_name') ?></label>
            <input type="text" id="name" name="name" class="form-control" value="<?= $e($alias->name) ?>"<?= $managed ? ' readonly' : '' ?> />
          </div>
          <div class="col-md-6">
            <label for="accessPolicy" class="form-label"><?= $te('alias.access_policy') ?></label>
            <select id="accessPolicy" name="accessPolicy" class="form-select">
              <option value="public"<?= $alias->accessPolicy === 'public' ? ' selected' : '' ?>><?= $te('alias.policy_public') ?></option>
              <option value="domain"<?= $alias->accessPolicy === 'domain' ? ' selected' : '' ?>><?= $te('alias.policy_domain') ?></option>
              <option value="membersOnly"<?= $alias->accessPolicy === 'membersOnly' ? ' selected' : '' ?>><?= $te('alias.policy_members') ?></option>
              <option value="moderatorsOnly"<?= $alias->accessPolicy === 'moderatorsOnly' ? ' selected' : '' ?>><?= $te('alias.policy_moderators') ?></option>
            </select>
          </div>
        </div>

        <div class="form-check form-switch mb-3">
          <input type="checkbox" class="form-check-input" id="active" name="active" <?= $alias->active ? 'checked' : '' ?> />
          <label class="form-check-label" for="active"><?= $te('common.active') ?></label>
        </div>

        <label for="members" class="form-label"><?= $te('alias.members_oneline') ?></label>
        <?php if ($managed): ?>
        <textarea id="members" rows="8" class="form-control" readonly><?= $e(implode("\n", $members)) ?></textarea>
        <?php else: ?>
        <textarea id="members" name="members" data-account-picker="multi" rows="8" class="form-control"><?= $e(implode("\n", $members)) ?></textarea>
        <?php endif; ?>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('mlist.save_settings') ?></button>
      </div>
    </form>

    <div class="card" id="section-members">
      <div class="card-header"><?= $te('alias.current_members') ?></div>
      <?php if (!$managed): ?>
      <div class="card-body">
        <form method="post" action="/aliases/<?= $e($alias->address) ?>" class="d-flex flex-wrap gap-2">
          <?= $csrfField ?>
          <input type="hidden" name="action" value="addMember" />
          <input type="email" name="newMember" data-account-picker="single" class="form-control flex-grow-1 w-auto" placeholder="user@example.com" required aria-label="<?= $te('alias.quick_add_member') ?>" />
          <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i><?= $te('alias.add_member') ?></button>
        </form>
      </div>
      <?php endif; ?>
      <?php if (!empty($members)): ?>
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr>
              <th><?= $te('common.email') ?></th>
              <th class="text-end"><?= $te('common.actions') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($members as $member): ?>
            <tr>
              <td><?= $e($member) ?></td>
              <td>
                <?php if (!$managed): ?>
                <form method="post" action="/aliases/<?= $e($alias->address) ?>" class="table-actions">
                  <?= $csrfField ?>
                  <input type="hidden" name="action" value="removeMember" />
                  <input type="hidden" name="member" value="<?= $e($member) ?>" />
                  <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="<?= $te('alias.remove_confirm', ['member' => $member]) ?>"><i class="bi bi-x-lg me-1"></i><?= $te('alias.remove') ?></button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <form method="post" action="/aliases/<?= $e($alias->address) ?>" class="card" id="section-moderators">
      <?= $csrfField ?>
      <input type="hidden" name="action" value="updateModerators" />
      <div class="card-header"><?= $te('alias.moderators') ?></div>
      <div class="card-body">
        <label for="moderators" class="form-label"><?= $te('alias.moderators_hint') ?></label>
        <textarea id="moderators" name="moderators" data-account-picker="multi" rows="4" class="form-control"><?= $e(implode("\n", $moderators)) ?></textarea>
        <div class="form-text"><?= $te('alias.moderators_desc') ?></div>
      </div>
      <div class="card-footer">
        <button type="submit" class="btn btn-primary"><?= $te('alias.save_moderators') ?></button>
      </div>
    </form>

    <a href="/aliases"><i class="bi bi-arrow-left me-1"></i><?= $te('alias.back') ?></a>
  </div>
</div>
