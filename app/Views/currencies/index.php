<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $canManage = user_can('masterdata.manage'); ?>

<div class="page-head">
  <div>
    <h1><?= lang('Setup.currencies_rates_h') ?></h1>
    <div class="muted small"><?= lang('Setup.ccy_note', ['<b>' . esc(company_name()) . '</b>', '<b>' . esc($baseCode) . '</b>']) ?></div>
  </div>
</div>

<div class="card">
  <h2><?= lang('App.currency') ?></h2>
  <table class="grid tight">
    <thead><tr><th><?= lang('Report.col_code') ?></th><th><?= lang('Report.col_name') ?></th><th><?= lang('Setup.symbol') ?></th><th class="center"><?= lang('Setup.decimals') ?></th><th><?= lang('Setup.role_here') ?></th><th><?= lang('App.status') ?></th></tr></thead>
    <tbody>
      <?php foreach ($currencies as $c): ?>
        <tr>
          <td class="mono"><?= esc($c['code']) ?></td>
          <td><?= esc($c['name']) ?></td>
          <td><?= esc($c['symbol']) ?></td>
          <td class="center"><?= (int) $c['decimal_places'] ?></td>
          <td><?= $c['code'] === $baseCode ? '<span class="badge badge-green">' . esc(lang('Setup.base_badge')) . '</span>' : esc(lang('Setup.foreign')) ?></td>
          <td><?= $c['is_active'] ? esc(lang('App.active')) : '<span class="muted">' . esc(lang('App.inactive')) . '</span>' ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>

  <?php if ($canManage): ?>
    <h2><?= lang('Setup.add_currency') ?></h2>
    <form method="post" action="<?= site_url('currencies') ?>" class="row">
      <?= csrf_field() ?>
      <div class="field" style="max-width:120px"><label><?= lang('Report.col_code') ?></label><input name="code" class="mono" maxlength="3" required></div>
      <div class="field"><label><?= lang('Report.col_name') ?></label><input name="name" required></div>
      <div class="field" style="max-width:110px"><label><?= lang('Setup.symbol') ?></label><input name="symbol"></div>
      <div class="field" style="max-width:110px"><label><?= lang('Setup.decimals') ?></label><input name="decimal_places" type="number" value="2" min="0" max="6"></div>
      <div class="field" style="max-width:120px;display:flex;align-items:flex-end"><button class="btn" type="submit"><?= lang('App.add') ?></button></div>
    </form>
  <?php endif ?>
</div>

<?php foreach ($currencies as $c): ?>
  <?php if ($c['code'] === $baseCode || ! $c['is_active']) {
      continue;
  } ?>
  <div class="card">
    <h2><?= lang('Setup.recent_rates', [esc($c['code'])]) ?> <span class="muted small"><?= lang('Setup.per_1_of', [esc($baseCode), esc($c['code'])]) ?></span></h2>
    <table class="grid tight mono" style="max-width:420px">
      <thead><tr><th><?= lang('App.date') ?></th><th class="right"><?= lang('Setup.rate') ?></th></tr></thead>
      <tbody>
        <?php foreach (($recent[$c['id']] ?? []) as $r): ?>
          <tr><td><?= date_id($r['rate_date']) ?></td><td class="right"><?= money($r['rate'], 4) ?></td></tr>
        <?php endforeach ?>
        <?php if (empty($recent[$c['id']])): ?><tr><td colspan="2" class="muted" style="font-family:sans-serif"><?= lang('Setup.no_rates_note') ?></td></tr><?php endif ?>
      </tbody>
    </table>
    <?php if ($canManage): ?>
      <form method="post" action="<?= site_url('currencies/' . $c['id'] . '/rate') ?>" class="row" style="margin-top:10px">
        <?= csrf_field() ?>
        <div class="field" style="max-width:180px"><label><?= lang('App.date') ?></label><input type="date" name="rate_date" value="<?= date('Y-m-d') ?>" required></div>
        <div class="field" style="max-width:240px"><label>1 <?= esc($c['code']) ?> = <?= esc($baseSymbol) ?></label><input name="rate" type="number" step="0.0001" min="0" required></div>
        <div class="field" style="max-width:120px;display:flex;align-items:flex-end"><button class="btn" type="submit"><?= lang('Setup.save_rate') ?></button></div>
      </form>
    <?php endif ?>
  </div>
<?php endforeach ?>

<?= $this->endSection() ?>
