<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$isEdit = $row !== null;
$action = $isEdit ? site_url('budgets/' . $row['id']) : site_url('budgets');
$v      = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
?>

<div class="page-head"><h1><?= esc($title) ?></h1></div>

<div class="card" style="max-width:520px">
  <form method="post" action="<?= $action ?>">
    <?= csrf_field() ?>
    <div class="row">
      <div class="field"><label><?= lang('Budget.f_name') ?></label><input name="name" maxlength="60" required value="<?= esc($v('name')) ?>"></div>
      <div class="field" style="max-width:120px"><label><?= lang('Budget.f_year') ?></label><input name="year" type="number" required value="<?= esc($v('year', date('Y'))) ?>"></div>
    </div>
    <div class="field"><label><?= lang('Budget.f_note') ?> <span class="muted"><?= lang('App.optional') ?></span></label><input name="note" maxlength="255" value="<?= esc($v('note')) ?>"></div>
    <label class="inline" style="font-weight:400">
      <input type="checkbox" name="is_default" value="1" style="width:auto" <?= $v('is_default', 0) ? 'checked' : '' ?>>
      <span><?= lang('Budget.f_default_hint') ?></span>
    </label>
    <div class="btn-group" style="margin-top:14px">
      <button class="btn" type="submit"><?= lang('App.save') ?></button>
      <a class="btn ghost" href="<?= site_url('budgets') ?>"><?= lang('App.cancel') ?></a>
    </div>
  </form>
</div>

<?= $this->endSection() ?>
