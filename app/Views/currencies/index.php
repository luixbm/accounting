<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $canManage = user_can('masterdata.manage'); ?>

<div class="page-head">
  <div>
    <h1>Currencies &amp; Exchange Rates</h1>
    <div class="muted small">Currencies are shared. Exchange rates are per company &mdash; below are the rates for <b><?= esc(company_name()) ?></b>, quoted against its base currency <b><?= esc($baseCode) ?></b>.</div>
  </div>
</div>

<div class="card">
  <h2>Currencies</h2>
  <table class="grid tight">
    <thead><tr><th>Code</th><th>Name</th><th>Symbol</th><th class="center">Decimals</th><th>Role here</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($currencies as $c): ?>
        <tr>
          <td class="mono"><?= esc($c['code']) ?></td>
          <td><?= esc($c['name']) ?></td>
          <td><?= esc($c['symbol']) ?></td>
          <td class="center"><?= (int) $c['decimal_places'] ?></td>
          <td><?= $c['code'] === $baseCode ? '<span class="badge badge-green">base</span>' : 'foreign' ?></td>
          <td><?= $c['is_active'] ? 'active' : '<span class="muted">inactive</span>' ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>

  <?php if ($canManage): ?>
    <h2>Add currency</h2>
    <form method="post" action="<?= site_url('currencies') ?>" class="row">
      <?= csrf_field() ?>
      <div class="field" style="max-width:120px"><label>Code</label><input name="code" class="mono" maxlength="3" required></div>
      <div class="field"><label>Name</label><input name="name" required></div>
      <div class="field" style="max-width:110px"><label>Symbol</label><input name="symbol"></div>
      <div class="field" style="max-width:110px"><label>Decimals</label><input name="decimal_places" type="number" value="2" min="0" max="6"></div>
      <div class="field" style="max-width:120px;display:flex;align-items:flex-end"><button class="btn" type="submit">Add</button></div>
    </form>
  <?php endif ?>
</div>

<?php foreach ($currencies as $c): ?>
  <?php if ($c['code'] === $baseCode || ! $c['is_active']) {
      continue;
  } ?>
  <div class="card">
    <h2><?= esc($c['code']) ?> — recent rates <span class="muted small">(<?= esc($baseCode) ?> per 1 <?= esc($c['code']) ?>)</span></h2>
    <table class="grid tight mono" style="max-width:420px">
      <thead><tr><th>Date</th><th class="right">Rate</th></tr></thead>
      <tbody>
        <?php foreach (($recent[$c['id']] ?? []) as $r): ?>
          <tr><td><?= date_id($r['rate_date']) ?></td><td class="right"><?= money($r['rate'], 4) ?></td></tr>
        <?php endforeach ?>
        <?php if (empty($recent[$c['id']])): ?><tr><td colspan="2" class="muted" style="font-family:sans-serif">No rates on file — 1.0 will be used.</td></tr><?php endif ?>
      </tbody>
    </table>
    <?php if ($canManage): ?>
      <form method="post" action="<?= site_url('currencies/' . $c['id'] . '/rate') ?>" class="row" style="margin-top:10px">
        <?= csrf_field() ?>
        <div class="field" style="max-width:180px"><label>Date</label><input type="date" name="rate_date" value="<?= date('Y-m-d') ?>" required></div>
        <div class="field" style="max-width:240px"><label>1 <?= esc($c['code']) ?> = <?= esc($baseSymbol) ?></label><input name="rate" type="number" step="0.0001" min="0" required></div>
        <div class="field" style="max-width:120px;display:flex;align-items:flex-end"><button class="btn" type="submit">Save rate</button></div>
      </form>
    <?php endif ?>
  </div>
<?php endforeach ?>

<?= $this->endSection() ?>
