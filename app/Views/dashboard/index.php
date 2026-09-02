<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php

use App\Libraries\Chart\Svg;

$labels    = array_column($series, 'label');
$sRevenue  = array_column($series, 'revenue');
$sExpense  = array_column($series, 'expense');
$sNet      = array_column($series, 'net');
$sCash     = array_column($series, 'cash');
?>

<div class="page-head">
  <div>
    <h1><?= lang('Dashboard.title') ?></h1>
    <form method="get" class="inline small no-print" style="margin-top:6px">
      <span class="muted"><?= lang('App.period') ?></span>
      <input type="date" name="from" value="<?= esc($from) ?>" style="width:auto;padding:4px 7px">
      <span class="muted">→</span>
      <input type="date" name="to" value="<?= esc($to) ?>" style="width:auto;padding:4px 7px">
      <button class="btn sm" type="submit"><?= lang('App.apply') ?></button>
      <a class="btn sm ghost" href="<?= site_url('dashboard') ?>"><?= lang('App.this_year') ?></a>
    </form>
  </div>
  <div class="btn-group no-print">
    <a class="btn" href="<?= site_url('journals/new') ?>"><?= lang('Dashboard.new_journal') ?></a>
  </div>
</div>

<div class="kpis">
  <div class="kpi">
    <div class="k-label"><?= lang('Dashboard.cash_bank') ?></div>
    <div class="k-value mono"><?= rupiah($cashTotal, false, 0) ?></div>
    <div class="small muted"><?= lang('Dashboard.as_of', [date_id($to)]) ?></div>
  </div>
  <div class="kpi">
    <div class="k-label"><?= lang('Dashboard.ar') ?></div>
    <div class="k-value mono"><?= rupiah($ar, false, 0) ?></div>
  </div>
  <div class="kpi">
    <div class="k-label"><?= lang('Dashboard.ap') ?></div>
    <div class="k-value mono"><?= rupiah($ap, false, 0) ?></div>
  </div>
  <div class="kpi">
    <div class="k-label"><?= lang('Dashboard.revenue') ?></div>
    <div class="k-value mono"><?= rupiah($revenue, false, 0) ?></div>
    <div class="small muted"><?= date_id($from) ?> – <?= date_id($to) ?></div>
  </div>
  <div class="kpi <?= $netIncome < 0 ? 'neg' : 'pos' ?>">
    <div class="k-label"><?= lang('Dashboard.net_income') ?></div>
    <div class="k-value mono"><?= rupiah($netIncome, false, 0) ?></div>
  </div>
</div>

<div class="chart-grid">
  <div class="card">
    <h2><?= lang('Dashboard.chart_rev_exp') ?> <span class="muted small"><?= lang('Dashboard.per_month') ?></span></h2>
    <?= Svg::groupedBars($labels, [lang('Dashboard.revenue') => $sRevenue, lang('Dashboard.expense') => $sExpense]) ?>
  </div>
  <div class="card">
    <h2><?= lang('Dashboard.chart_net') ?> <span class="muted small"><?= lang('Dashboard.per_month') ?></span></h2>
    <?= Svg::signedBars($labels, $sNet) ?>
  </div>
  <div class="card">
    <h2><?= lang('Dashboard.chart_cash') ?> <span class="muted small"><?= lang('Dashboard.month_end') ?></span></h2>
    <?= Svg::area($labels, $sCash) ?>
  </div>
  <div class="card">
    <h2><?= lang('Dashboard.chart_exp_break') ?> <span class="muted small"><?= date_id($from) ?> – <?= date_id($to) ?></span></h2>
    <?= Svg::hBars($breakdown) ?>
  </div>
</div>

<div class="row">
  <div class="card" style="flex:1;min-width:280px">
    <h2><?= lang('Dashboard.cash_accounts') ?></h2>
    <table class="grid tight">
      <tbody>
        <?php foreach ($cashRows as $r): ?>
          <tr>
            <td><?= esc($r['name']) ?></td>
            <td class="right mono"><?= rupiah($r['balance']) ?></td>
          </tr>
        <?php endforeach ?>
        <?php if (! $cashRows): ?>
          <tr><td class="muted"><?= lang('App.no_records') ?></td></tr>
        <?php endif ?>
      </tbody>
      <tfoot>
        <tr><td><?= lang('Dashboard.total') ?></td><td class="right mono"><?= rupiah($cashTotal) ?></td></tr>
      </tfoot>
    </table>
  </div>

  <div class="card" style="flex:2;min-width:340px">
    <h2><?= lang('Dashboard.recent') ?> <?php if ($draftCount): ?><span class="badge badge-gray"><?= $draftCount ?> <?= lang('App.draft') ?></span><?php endif ?></h2>
    <table class="grid tight">
      <thead>
        <tr><th><?= lang('Dashboard.col_no') ?></th><th><?= lang('Dashboard.col_date') ?></th><th><?= lang('Dashboard.col_desc') ?></th><th class="right"><?= lang('Dashboard.col_amount') ?></th><th><?= lang('Dashboard.col_status') ?></th></tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $j): ?>
          <tr>
            <td class="nowrap"><a href="<?= site_url('journals/' . $j['id']) ?>"><?= esc($j['journal_no']) ?></a></td>
            <td class="nowrap"><?= date_id($j['entry_date']) ?></td>
            <td><?= esc($j['description']) ?></td>
            <td class="right mono"><?= rupiah($j['total_debit']) ?></td>
            <td><?= status_badge($j['status']) ?></td>
          </tr>
        <?php endforeach ?>
        <?php if (! $recent): ?>
          <tr><td colspan="5" class="muted"><?= lang('Dashboard.no_journals') ?> <a href="<?= site_url('journals/new') ?>"><?= lang('Dashboard.create_first') ?></a></td></tr>
        <?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
