<?php
/**
 * Shared report filter bar.
 *
 * @var array       $f        ReportFilter::resolve() output
 * @var bool|null   $showAsOf
 * @var bool|null   $showCompare
 * @var bool|null   $showZeros  offer "include accounts with no activity"
 * @var string|null $extra    extra <div class="field">…</div> markup (account picker etc.)
 */
use App\Libraries\Report\ReportFilter;

$years   = ReportFilter::years();
$isCustom = $f['period'] === 'custom';
?>
<form class="filterbar no-print" method="get" id="reportFilter">
  <div class="field" style="max-width:110px">
    <label><?= lang('App.year') ?></label>
    <select name="year">
      <?php foreach ($years as $y): ?>
        <option value="<?= $y ?>" <?= $f['year'] === $y ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="field" style="max-width:170px">
    <label><?= lang('App.period') ?></label>
    <select name="period" id="periodSel">
      <option value="year" <?= $f['period'] === 'year' ? 'selected' : '' ?>><?= lang('App.full_year') ?></option>
      <?php for ($q = 1; $q <= 4; $q++): ?>
        <option value="q<?= $q ?>" <?= $f['period'] === "q{$q}" ? 'selected' : '' ?>>Q<?= $q ?></option>
      <?php endfor ?>
      <?php for ($m = 1; $m <= 12; $m++): ?>
        <option value="m<?= $m ?>" <?= $f['period'] === "m{$m}" ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
      <?php endfor ?>
      <option value="custom" <?= $isCustom ? 'selected' : '' ?>><?= lang('App.custom') ?></option>
    </select>
  </div>
  <div class="field customRange" style="max-width:160px;<?= $isCustom ? '' : 'display:none' ?>">
    <label><?= lang('App.from') ?></label><input type="date" name="from" value="<?= esc($f['from']) ?>">
  </div>
  <div class="field customRange" style="max-width:160px;<?= $isCustom ? '' : 'display:none' ?>">
    <label><?= lang('App.to') ?></label><input type="date" name="to" value="<?= esc($f['to']) ?>">
  </div>

  <?php if (! empty($showAsOf)): ?>
    <div class="field" style="max-width:160px">
      <label><?= lang('App.as_of') ?></label><input type="date" name="as_of" value="<?= esc($f['asOf']) ?>">
    </div>
  <?php endif ?>

  <?php if (! empty($showCompare)): ?>
    <div class="field" style="max-width:150px">
      <label><?= lang('App.compare_by') ?></label>
      <select name="compare">
        <option value=""><?= lang('App.none') ?></option>
        <option value="month" <?= $f['compare'] === 'month' ? 'selected' : '' ?>><?= lang('App.month') ?></option>
        <option value="quarter" <?= $f['compare'] === 'quarter' ? 'selected' : '' ?>><?= lang('App.quarter') ?></option>
        <option value="year" <?= $f['compare'] === 'year' ? 'selected' : '' ?>><?= lang('App.year') ?></option>
      </select>
    </div>
  <?php endif ?>

  <?= $extra ?? '' ?>

  <?php if (! empty($showZeros)): ?>
    <label class="field inline" style="align-self:center;gap:6px;white-space:nowrap">
      <input type="checkbox" name="zeros" value="1" style="width:auto" <?= ! empty($f['zeros']) ? 'checked' : '' ?>>
      <span class="small">All chart accounts</span>
    </label>
  <?php endif ?>

  <button class="btn" type="submit"><?= lang('App.apply') ?></button>
  <button class="btn ghost" type="submit" name="format" value="xlsx"><?= lang('App.export_xlsx') ?></button>
  <button class="btn secondary" type="button" onclick="window.print()"><?= lang('App.print') ?></button>
</form>
<script>
  document.getElementById('periodSel').addEventListener('change', function () {
    var show = this.value === 'custom';
    document.querySelectorAll('#reportFilter .customRange').forEach(function (el) { el.style.display = show ? '' : 'none'; });
  });
</script>
