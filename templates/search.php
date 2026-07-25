<?php $pageTitle = $t('search.title'); ?>
<div class="container">
  <div class="row">
    <div class="col">
      <h1><?= $te('search.title') ?></h1>

      <form method="get" action="/search">
        <div class="row">
          <div class="col-6">
            <input type="text" name="q" value="<?= $e($query) ?>" placeholder="<?= $te('search.placeholder') ?>" autofocus />
          </div>
          <div class="col-3">
            <select name="accountType[]" multiple>
              <option value=""><?= $te('search.all_types') ?></option>
              <option value="domain" <?= in_array('domain', $accountTypes) ? 'selected' : '' ?>><?= $te('domain.list_title') ?></option>
              <option value="user" <?= in_array('user', $accountTypes) ? 'selected' : '' ?>><?= $te('user.list_title') ?></option>
              <option value="alias" <?= in_array('alias', $accountTypes) ? 'selected' : '' ?>><?= $te('search.type_aliases') ?></option>
              <option value="ml" <?= in_array('ml', $accountTypes) ? 'selected' : '' ?>><?= $te('mlist.list_title') ?></option>
              <option value="admin" <?= in_array('admin', $accountTypes) ? 'selected' : '' ?>><?= $te('admin.list_title') ?></option>
            </select>
          </div>
          <div class="col-3">
            <button type="submit" class="button primary"><?= $te('search.title') ?></button>
          </div>
        </div>
      </form>

      <?php if ($results !== null): ?>

      <?php
        $totalResults = count($results['domains'] ?? []) + count($results['users'] ?? [])
          + count($results['aliases'] ?? []) + count($results['mailingLists'] ?? [])
          + count($results['admins'] ?? []);
      ?>
      <p class="text-light"><?= $te('search.results_for', ['count' => $totalResults, 'query' => $query]) ?></p>

      <?php if (!empty($results['domains'])): ?>
      <h3><?= $te('domain.list_title') ?> (<?= count($results['domains']) ?>)</h3>
      <table class="striped">
        <thead><tr><th><?= $te('common.domain') ?></th><th><?= $te('common.description') ?></th><th><?= $te('common.status') ?></th></tr></thead>
        <tbody>
          <?php foreach ($results['domains'] as $d): ?>
          <tr>
            <td><a href="/domains/<?= $e($d['domain']) ?>/edit"><?= $e($d['domain']) ?></a></td>
            <td><?= $e($d['description'] ?? '') ?></td>
            <td><?= $localize(($d['active'] ?? 1) ? 'active' : 'disabled') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>

      <?php if (!empty($results['users'])): ?>
      <h3><?= $te('user.list_title') ?> (<?= count($results['users']) ?>)</h3>
      <table class="striped">
        <thead><tr><th><?= $te('common.email') ?></th><th><?= $te('common.name') ?></th><th><?= $te('common.domain') ?></th><th><?= $te('common.status') ?></th></tr></thead>
        <tbody>
          <?php foreach ($results['users'] as $u): ?>
          <?php $uid = str_contains($u['username'], '@') ? explode('@', $u['username'])[0] : $u['username']; ?>
          <tr>
            <td><a href="/<?= $e($u['domain']) ?>/users/<?= $e($uid) ?>/general"><?= $e($u['username']) ?></a></td>
            <td><?= $e($u['name'] ?? '') ?></td>
            <td><?= $e($u['domain'] ?? '') ?></td>
            <td><?= $localize(($u['active'] ?? 1) ? 'active' : 'disabled') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>

      <?php if (!empty($results['aliases'])): ?>
      <h3><?= $te('search.type_aliases') ?> (<?= count($results['aliases']) ?>)</h3>
      <table class="striped">
        <thead><tr><th><?= $te('common.address') ?></th><th><?= $te('common.name') ?></th><th><?= $te('common.domain') ?></th><th><?= $te('common.status') ?></th></tr></thead>
        <tbody>
          <?php foreach ($results['aliases'] as $a): ?>
          <tr>
            <td><a href="/aliases/<?= $e($a['address']) ?>"><?= $e($a['address']) ?></a></td>
            <td><?= $e($a['name'] ?? '') ?></td>
            <td><?= $e($a['domain'] ?? '') ?></td>
            <td><?= $localize(($a['active'] ?? 1) ? 'active' : 'disabled') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>

      <?php if (!empty($results['mailingLists'])): ?>
      <h3><?= $te('mlist.list_title') ?> (<?= count($results['mailingLists']) ?>)</h3>
      <table class="striped">
        <thead><tr><th><?= $te('common.address') ?></th><th><?= $te('common.name') ?></th><th><?= $te('common.domain') ?></th><th><?= $te('common.status') ?></th></tr></thead>
        <tbody>
          <?php foreach ($results['mailingLists'] as $ml): ?>
          <tr>
            <td><a href="/mailing-lists/<?= $e($ml['address']) ?>"><?= $e($ml['address']) ?></a></td>
            <td><?= $e($ml['name'] ?? '') ?></td>
            <td><?= $e($ml['domain'] ?? '') ?></td>
            <td><?= $localize(($ml['active'] ?? 1) ? 'active' : 'disabled') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>

      <?php if (!empty($results['admins'])): ?>
      <h3><?= $te('admin.list_title') ?> (<?= count($results['admins']) ?>)</h3>
      <table class="striped">
        <thead><tr><th><?= $te('common.email') ?></th><th><?= $te('common.name') ?></th><th><?= $te('common.status') ?></th></tr></thead>
        <tbody>
          <?php foreach ($results['admins'] as $adm): ?>
          <tr>
            <td><a href="/admins/<?= $e($adm['username']) ?>/general"><?= $e($adm['username']) ?></a></td>
            <td><?= $e($adm['name'] ?? '') ?></td>
            <td><?= $localize(($adm['active'] ?? 1) ? 'active' : 'disabled') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>

      <?php if ($totalResults === 0): ?>
      <p class="text-light"><?= $te('common.no_results') ?></p>
      <?php endif; ?>

      <?php endif; ?>
    </div>
  </div>
</div>
