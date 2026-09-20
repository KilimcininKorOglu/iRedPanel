<?php
// How long a deleted mailbox stays on disk; the single and the bulk delete of the form read it.
// A domain admin gets the longest time preselected, a global admin "forever".
$keepOptions = \App\Models\KeepMailboxDays::options(!empty($session['isGlobalAdmin']));
$keepSelected = !empty($session['isGlobalAdmin']) ? 0 : max($keepOptions);
?>
<label for="keepMailboxDays" class="small text-body-secondary"><?= $te('common.keep_mailbox_days') ?></label><?= $help('common.keep_mailbox_days') ?>
<select id="keepMailboxDays" name="keepMailboxDays" class="form-select form-select-sm w-auto">
  <?php foreach ($keepOptions as $days): ?>
  <option value="<?= $days ?>"<?= $days === $keepSelected ? ' selected' : '' ?>><?= $days === 0 ? $te('common.keep_forever') : $days ?></option>
  <?php endforeach; ?>
</select>
