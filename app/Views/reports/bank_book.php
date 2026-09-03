<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<?php
$acctPicker = '<div class="field" style="min-width:260px"><label>' . lang('App.account') . '</label><select name="account_id">';
foreach ($banks as $b) {
    $acctPicker .= '<option value="' . $b['id'] . '"' . ($accountId === (int) $b['id'] ? ' selected' : '') . '>'
        . esc($b['code'] . ' · ' . $b['name']) . '</option>';
}
$acctPicker .= '</select></div>';
?>
<div class="page-head"><h1><?= esc($title) ?></h1><div class="btn-group no-print"><a class="btn ghost" href="<?= site_url('reports') ?>">&lsaquo; <?= lang('Report.v_all_reports') ?></a></div></div>
<?= view('reports/_period', ['f' => $f, 'extra' => $acctPicker]) ?>

<?php if ($account && $data): ?>
  <?php
  $totIn  = array_sum(array_column($data['lines'], 'debit'));
  $totOut = array_sum(array_column($data['lines'], 'credit'));
  ?>
  <div class="report-title">
    <?php if ($u = company_logo_url()): ?><img src="<?= esc($u) ?>"><?php endif ?>
    <div class="co"><?= esc(company_name()) ?></div>
    <h1><?= esc($title) ?> — <?= esc($account["code"] . " " . $account["name"]) ?></h1>
    <div class="muted"><?= date_id($from) ?> — <?= date_id($to) ?></div>
  </div>

  <div class="kpis">
    <div class="kpi"><div class="k-label"><?= lang('Report.v_opening') ?></div><div class="k-value mono"><?= rupiah($data['opening'], false, 0) ?></div></div>
    <div class="kpi pos"><div class="k-label"><?= lang('Report.v_money_in') ?></div><div class="k-value mono"><?= rupiah($totIn, false, 0) ?></div></div>
    <div class="kpi neg"><div class="k-label"><?= lang('Report.v_money_out') ?></div><div class="k-value mono"><?= rupiah($totOut, false, 0) ?></div></div>
    <div class="kpi"><div class="k-label"><?= lang('Report.v_closing') ?></div><div class="k-value mono"><?= rupiah($data['closing'], false, 0) ?></div></div>
  </div>

  <div class="card">
    <table class="grid tight mono">
      <thead>
        <tr><th><?= lang('App.date') ?></th><th><?= lang('Report.v_journal') ?></th><th style="font-family:sans-serif"><?= lang('App.memo') ?></th><th style="font-family:sans-serif"><?= lang('Report.v_party') ?></th>
          <th class="right"><?= lang('Report.v_in') ?></th><th class="right"><?= lang('Report.v_out') ?></th><th class="right"><?= lang('Report.v_balance') ?></th></tr>
      </thead>
      <tbody>
        <tr class="subtotal"><td colspan="6"><?= lang('Report.v_opening_balance') ?></td><td class="right"><?= money($data['opening']) ?></td></tr>
        <?php foreach ($data['lines'] as $l): ?>
          <tr>
            <td class="nowrap"><?= date_id($l['entry_date']) ?></td>
            <td class="nowrap"><a href="<?= site_url('journals/' . $l['journal_id']) ?>"><?= esc($l['journal_no']) ?></a></td>
            <td style="font-family:sans-serif"><?= esc($l['memo']) ?></td>
            <td style="font-family:sans-serif" class="small"><?= esc($l['customer_name'] ?? $l['supplier_name'] ?? '') ?></td>
            <td class="right"><?= money($l['debit'], 2, true) ?></td>
            <td class="right"><?= money($l['credit'], 2, true) ?></td>
            <td class="right"><?= money($l['balance']) ?></td>
          </tr>
        <?php endforeach ?>
        <?php if (! $data['lines']): ?><tr><td colspan="7" class="muted" style="font-family:sans-serif"><?= lang('Report.v_empty_movement') ?></td></tr><?php endif ?>
      </tbody>
      <tfoot>
        <tr><td colspan="4" class="right"><?= lang('App.total') ?></td><td class="right"><?= money($totIn) ?></td><td class="right"><?= money($totOut) ?></td><td></td></tr>
        <tr><td colspan="6" class="right"><?= lang('Report.v_closing_balance') ?></td><td class="right"><?= money($data['closing']) ?></td></tr>
      </tfoot>
    </table>
  </div>
<?php else: ?>
  <div class="card"><p class="muted"><?= lang('Report.v_no_cash_accounts') ?></p></div>
<?php endif ?>

<?= $this->endSection() ?>
