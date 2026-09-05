<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div>
    <h1><?= esc($job['code']) ?> — <?= esc($job['name']) ?></h1>
    <div class="muted small">
      <?= esc($job['status'] === 'open' ? lang('Txn.open') : lang('Txn.closed')) ?><?= $job['customer_name'] ? ' · ' . esc($job['customer_name']) : '' ?>
      <?= $job['start_date'] ? ' · ' . date_id($job['start_date']) : '' ?><?= $job['end_date'] ? ' – ' . date_id($job['end_date']) : '' ?>
    </div>
  </div>
  <div class="btn-group no-print">
    <a class="btn ghost" href="<?= site_url('jobs/' . $job['id'] . '/edit') ?>"><?= lang('App.edit') ?></a>
    <form method="post" action="<?= site_url('jobs/' . $job['id'] . '/toggle') ?>" style="display:inline">
      <?= csrf_field() ?><button class="btn ghost" type="submit"><?= $job['status'] === 'open' ? lang('Txn.close_job') : lang('Txn.reopen') ?></button>
    </form>
    <button class="btn secondary" onclick="window.print()"><?= lang('App.print') ?></button>
    <a class="btn ghost" href="<?= site_url('jobs') ?>"><?= lang('App.back') ?></a>
  </div>
</div>

<form class="filterbar no-print" method="get">
  <div class="field"><label><?= lang('App.from') ?></label><input type="date" name="from" value="<?= esc($from) ?>"></div>
  <div class="field"><label><?= lang('App.to') ?></label><input type="date" name="to" value="<?= esc($to) ?>"></div>
  <button class="btn" type="submit"><?= lang('App.apply') ?></button>
  <a class="btn ghost" href="<?= site_url('jobs/' . $job['id']) ?>"><?= lang('Txn.all_time') ?></a>
</form>

<div class="kpis">
  <div class="kpi"><div class="k-label"><?= lang('Txn.revenue') ?></div><div class="k-value mono"><?= money_c($pl['revenue'], false, 0) ?></div></div>
  <div class="kpi"><div class="k-label"><?= lang('Txn.direct_cost') ?></div><div class="k-value mono"><?= money_c($pl['direct_cost'], false, 0) ?></div></div>
  <div class="kpi <?= $pl['gross_profit'] < 0 ? 'neg' : 'pos' ?>"><div class="k-label"><?= lang('Txn.gross_profit_k') ?></div><div class="k-value mono"><?= money_c($pl['gross_profit'], false, 0) ?></div></div>
  <div class="kpi <?= $pl['net'] < 0 ? 'neg' : 'pos' ?>"><div class="k-label"><?= lang('Txn.net_profit_k') ?></div><div class="k-value mono"><?= money_c($pl['net'], false, 0) ?></div></div>
  <div class="kpi"><div class="k-label"><?= lang('Txn.margin') ?></div><div class="k-value mono"><?= number_format($pl['margin'], 1) ?>%</div></div>
</div>

<?php
$jbxSales  = (float) ($job['sales_ref'] ?? 0);
$hasJbx    = ($job['sales_ref'] ?? null) !== null || ($job['buy_ref'] ?? null) !== null;
$hasJbxCard = ($job['pax'] ?? null) !== null || ($job['category'] ?? null) !== null
    || ($job['jambix_status'] ?? null) !== null || ($job['created_on'] ?? null) !== null;
// Purchase Jambix column = per-supplier booking budget, so the Total foots to
// the rows. Sales has no per-supplier budget, so its Total stays the job quote.
$totBudget = (float) $partyBreakdown['total_purchase_budget'];
$totDiff   = $totBudget - (float) $partyBreakdown['total_purchase'];
$jbxMargin = $jbxSales != 0.0 ? ($jbxSales - $totBudget) / $jbxSales * 100 : null;
?>

<div class="card" style="max-width:1100px">
  <div style="overflow-x:auto">
  <table class="grid tight">
    <thead>
      <tr>
        <th><?= lang('Report.col_cust_supp') ?></th>
        <th class="right"><?= lang('Report.col_sales_jambix') ?></th>
        <th class="right"><?= lang('Report.col_purchase_jambix') ?></th>
        <th class="right"><?= lang('Report.col_gop') ?></th>
        <th class="right"><?= lang('Report.col_actual_sales') ?></th>
        <th class="right"><?= lang('Report.col_actual_purchase') ?></th>
        <th class="right"><?= lang('Report.col_gop_actual') ?></th>
        <th class="right"><?= lang('Report.col_diff_cost') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($partyBreakdown['rows'] as $r): ?>
        <?php
        $rowHasBuy  = abs($r['purchase']) >= 0.005 || abs($r['purchase_budget']) >= 0.005;
        $rowDiff    = $r['purchase_budget'] - $r['purchase'];
        ?>
        <tr>
          <td><?= esc($r['party']) ?></td>
          <td class="right mono">—</td>
          <td class="right mono"><?= abs($r['purchase_budget']) >= 0.005 ? money($r['purchase_budget']) : '' ?></td>
          <td class="right mono">—</td>
          <td class="right mono"><?= abs($r['sales']) >= 0.005 ? money($r['sales']) : '' ?></td>
          <td class="right mono"><?= abs($r['purchase']) >= 0.005 ? money($r['purchase']) : '' ?></td>
          <td class="right mono">—</td>
          <td class="right mono" style="<?= $rowHasBuy && abs($rowDiff) >= 0.005 ? ($rowDiff < 0 ? 'color:var(--red)' : 'color:var(--green)') : '' ?>"><?= $rowHasBuy ? money($rowDiff) : '' ?></td>
        </tr>
      <?php endforeach ?>
      <?php if (! $partyBreakdown['rows']): ?>
        <tr><td colspan="8" class="muted"><?= lang('Txn.no_job_lines') ?></td></tr>
      <?php else: ?>
        <tr class="subtotal">
          <td><?= lang('App.total') ?></td>
          <td class="right mono"><?= $hasJbx ? money($jbxSales, 0) : '—' ?></td>
          <td class="right mono"><?= $totBudget >= 0.005 ? money($totBudget, 0) : '—' ?></td>
          <td class="right mono"><?= $jbxMargin === null ? '—' : number_format($jbxMargin, 1) . '%' ?></td>
          <td class="right mono"><?= money($partyBreakdown['total_sales']) ?></td>
          <td class="right mono"><?= money($partyBreakdown['total_purchase']) ?></td>
          <td class="right mono"><?= number_format($pl['margin'], 1) ?>%</td>
          <td class="right mono" style="<?= abs($totDiff) >= 0.005 ? ($totDiff < 0 ? 'color:var(--red)' : 'color:var(--green)') : '' ?>"><?= $totBudget >= 0.005 ? money($totDiff) : '—' ?></td>
        </tr>
      <?php endif ?>
    </tbody>
  </table>
  </div>
  <?php if ($hasJbx): ?>
    <p class="muted small" style="margin:.6rem 0 0"><?= lang('Report.jbx_total_note') ?></p>
  <?php endif ?>
</div>

<?php if ($hasJbxCard): ?>
  <div class="card" style="max-width:520px">
    <h2><?= lang('Txn.jambix_ref') ?> <span class="muted small" style="font-weight:400">— <?= lang('Txn.jbx_ref_note') ?></span></h2>
    <table class="grid tight">
      <tbody>
        <?php if (($job['pax'] ?? null) !== null): ?><tr><td class="muted"><?= lang('Txn.pax') ?></td><td class="right mono"><?= (int) $job['pax'] ?></td></tr><?php endif ?>
        <?php if (($job['category'] ?? null) !== null): ?><tr><td class="muted"><?= lang('Txn.category') ?></td><td><?= esc($job['category']) ?></td></tr><?php endif ?>
        <?php if (($job['jambix_status'] ?? null) !== null): ?><tr><td class="muted"><?= lang('Txn.jambix_status') ?></td><td><?= esc($job['jambix_status']) ?></td></tr><?php endif ?>
        <?php if (($job['created_on'] ?? null) !== null): ?><tr><td class="muted"><?= lang('Txn.created_on') ?></td><td><?= date_id($job['created_on']) ?></td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
<?php endif ?>

<?php if (! empty($cfDefs) && array_filter($cfValues ?? [])): ?>
  <div class="card" style="max-width:520px">
    <h2><?= lang('App.additional_info') ?></h2>
    <table class="grid tight">
      <tbody>
        <?php foreach ($cfDefs as $d): ?>
          <?php if (($cfValues[$d['field_key']] ?? '') === '') {
              continue;
          } ?>
          <tr><td class="muted"><?= esc($d['label']) ?></td><td><?= esc($cfValues[$d['field_key']]) ?></td></tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php endif ?>

<?= $this->endSection() ?>
