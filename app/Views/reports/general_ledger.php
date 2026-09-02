<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<?php
$acctPicker = '<div class="field" style="min-width:260px"><label>' . lang('App.account') . '</label><select name="account_id">';
foreach ($accounts as $a) {
    $acctPicker .= '<option value="' . $a['id'] . '"' . ($accountId === (int) $a['id'] ? ' selected' : '') . '>'
        . esc($a['code'] . ' · ' . $a['name']) . '</option>';
}
$acctPicker .= '</select></div>';
?>
<div class="page-head"><h1><?= esc($title) ?></h1><div class="btn-group no-print"><a class="btn ghost" href="<?= site_url('reports') ?>">&lsaquo; All reports</a></div></div>
<?= view('reports/_period', ['f' => $f, 'extra' => $acctPicker]) ?>

<?php if ($account && $data): ?>
  <div class="report-title">
    <div class="co"><?= esc(company_name()) ?></div>
    <h1><?= esc($title) ?> — <?= esc($account["code"] . " " . $account["name"]) ?></h1>
    <div class="muted"><?= date_id($from) ?> — <?= date_id($to) ?></div>
  </div>

  <div class="card">
    <table class="grid tight mono">
      <thead>
        <tr><th>Date</th><th>Journal</th><th style="font-family:sans-serif">Memo</th><th style="font-family:sans-serif">Party</th>
          <th class="right">Debit</th><th class="right">Credit</th><th class="right">Balance</th></tr>
      </thead>
      <tbody>
        <tr class="subtotal"><td colspan="6">Opening balance</td><td class="right"><?= money($data['opening']) ?></td></tr>
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
        <?php if (! $data['lines']): ?><tr><td colspan="7" class="muted" style="font-family:sans-serif">No postings in this range.</td></tr><?php endif ?>
      </tbody>
      <tfoot>
        <tr><td colspan="6">Closing balance</td><td class="right"><?= money($data['closing']) ?></td></tr>
      </tfoot>
    </table>
  </div>
<?php endif ?>

<?= $this->endSection() ?>
