<select name="status" class="form-select" data-autosubmit aria-label="<?= $te('common.status') ?>">
  <?php foreach (['' => 'common.all', 'active' => 'common.active', 'disabled' => 'common.disabled'] as $status => $label): ?>
  <option value="<?= $e($status) ?>"<?= ($statusFilter ?? '') === $status ? ' selected' : '' ?>><?= $te($label) ?></option>
  <?php endforeach; ?>
</select>
