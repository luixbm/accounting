<?php
/**
 * @var string      $from
 * @var string      $to
 * @var string      $compare   '' | month | quarter | year
 * @var bool|null   $showAsOf
 * @var string|null $asOf
 */
?>
<form class="filterbar no-print" method="get">
  <?php if (! empty($showAsOf)): ?>
    <div class="field"><label>As of <span class="muted">(single)</span></label>
      <input type="date" name="as_of" value="<?= esc($asOf ?? date('Y-m-d')) ?>"></div>
  <?php endif ?>
  <div class="field"><label>From</label><input type="date" name="from" value="<?= esc($from) ?>"></div>
  <div class="field"><label>To</label><input type="date" name="to" value="<?= esc($to) ?>"></div>
  <div class="field">
    <label>Compare by</label>
    <select name="compare">
      <option value="">— none (single) —</option>
      <option value="month" <?= $compare === 'month' ? 'selected' : '' ?>>Month</option>
      <option value="quarter" <?= $compare === 'quarter' ? 'selected' : '' ?>>Quarter</option>
      <option value="year" <?= $compare === 'year' ? 'selected' : '' ?>>Year</option>
    </select>
  </div>
  <button class="btn" type="submit">Apply</button>
</form>
