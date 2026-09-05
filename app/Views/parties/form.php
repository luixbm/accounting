<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$v      = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
$action = $row ? site_url($route . '/' . $row['id']) : site_url($route);
?>

<div class="page-head"><h1><?= esc($title) ?></h1></div>

<div class="card" style="max-width:620px">
  <form method="post" action="<?= $action ?>">
    <?= csrf_field() ?>
    <div class="row">
      <div class="field" style="max-width:180px">
        <label><?= lang('Report.col_code') ?></label>
        <input name="code" class="mono" value="<?= esc($v('code')) ?>" required>
      </div>
      <div class="field">
        <label><?= lang('Report.col_name') ?></label>
        <input name="name" value="<?= esc($v('name')) ?>" required>
      </div>
    </div>
    <div class="row">
      <div class="field"><label>Email</label><input name="email" type="email" value="<?= esc($v('email')) ?>"></div>
      <div class="field"><label>Phone</label><input name="phone" value="<?= esc($v('phone')) ?>"></div>
      <div class="field"><label>NPWP</label><input name="npwp" class="mono" value="<?= esc($v('npwp')) ?>"></div>
    </div>
    <?php if ($route === 'customers'): ?>
      <div class="row">
        <div class="field"><label><?= lang('Report.col_client_group') ?></label><input name="client_group" value="<?= esc($v('client_group')) ?>"></div>
        <div class="field"><label><?= lang('Report.col_country') ?></label><input name="country" value="<?= esc($v('country')) ?>"></div>
      </div>
    <?php endif ?>
    <div class="field"><label><?= lang('Setup.address') ?></label><textarea name="address" rows="2"><?= esc($v('address')) ?></textarea></div>
    <label class="inline" style="font-weight:400">
      <input type="checkbox" name="is_active" value="1" style="width:auto" <?= old('is_active', $row['is_active'] ?? 1) ? 'checked' : '' ?>> <?= lang('App.active') ?>
    </label>
    <?php if (! empty($cfDefs)): ?>
      <?= view('partials/custom_fields', ['cfDefs' => $cfDefs, 'cfValues' => $cfValues]) ?>
    <?php endif ?>
    <div class="btn-group" style="margin-top:14px">
      <button class="btn" type="submit"><?= lang('App.save') ?></button>
      <a class="btn ghost" href="<?= site_url($route) ?>"><?= lang('App.cancel') ?></a>
    </div>
  </form>
</div>

<?= $this->endSection() ?>
