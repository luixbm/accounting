<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$v      = static fn (string $k, $d = '') => old($k, $job[$k] ?? $d);
$action = $job ? site_url('jobs/' . $job['id']) : site_url('jobs');
?>

<div class="page-head"><h1><?= esc($title) ?></h1></div>

<div class="card" style="max-width:640px">
  <form method="post" action="<?= $action ?>">
    <?= csrf_field() ?>
    <div class="row">
      <div class="field" style="max-width:200px"><label><?= lang('Report.col_code') ?></label><input name="code" class="mono" value="<?= esc($v('code')) ?>" required></div>
      <div class="field"><label><?= lang('Report.col_name') ?></label><input name="name" value="<?= esc($v('name')) ?>" required></div>
    </div>
    <div class="field">
      <label><?= lang('Txn.customer') ?></label>
      <select name="customer_id">
        <option value=""><?= lang('App.none') ?></option>
        <?php foreach ($customers as $c): ?>
          <option value="<?= $c['id'] ?>" <?= (string) $v('customer_id') === (string) $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
        <?php endforeach ?>
      </select>
    </div>
    <div class="field"><label><?= lang('App.description') ?></label><input name="description" value="<?= esc($v('description')) ?>"></div>
    <div class="row">
      <div class="field"><label><?= lang('Txn.start_date') ?></label><input type="date" name="start_date" value="<?= esc($v('start_date')) ?>"></div>
      <div class="field"><label><?= lang('Txn.end_date') ?></label><input type="date" name="end_date" value="<?= esc($v('end_date')) ?>"></div>
      <div class="field" style="max-width:160px">
        <label><?= lang('App.status') ?></label>
        <select name="status">
          <option value="open" <?= $v('status', 'open') === 'open' ? 'selected' : '' ?>><?= lang('Txn.open') ?></option>
          <option value="closed" <?= $v('status') === 'closed' ? 'selected' : '' ?>><?= lang('Txn.closed') ?></option>
        </select>
      </div>
    </div>
    <?php if (! empty($cfDefs)): ?>
      <?= view('partials/custom_fields', ['cfDefs' => $cfDefs, 'cfValues' => $cfValues]) ?>
    <?php endif ?>
    <div class="btn-group">
      <button class="btn" type="submit"><?= lang('App.save') ?></button>
      <a class="btn ghost" href="<?= site_url('jobs') ?>"><?= lang('App.cancel') ?></a>
    </div>
  </form>
</div>

<?= $this->endSection() ?>
