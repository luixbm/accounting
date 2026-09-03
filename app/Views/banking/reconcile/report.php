<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
/** @var array $st @var array $bank @var array $sum */
?>
<div class="page-head no-print">
  <div><h1>Bank Reconciliation Statement</h1></div>
  <div class="btn-group">
    <a class="btn ghost" href="<?= site_url('banking/reconcile/' . $st['id']) ?>">&lsaquo; Back</a>
    <button class="btn" type="button" onclick="window.print()">Print</button>
  </div>
</div>

<div class="report-title">
  <?php if ($u = company_logo_url()): ?><img src="<?= esc($u) ?>"><?php endif ?>
  <div class="co"><?= esc(company_name()) ?></div>
  <h1>Bank Reconciliation — <?= esc($bank['code'] . ' ' . $bank['name']) ?></h1>
  <div class="muted">As of <?= date_id($st['statement_date']) ?><?= $st['status'] === 'reconciled' ? ' · reconciled' : ' · draft' ?></div>
</div>

<div class="card">
  <table class="grid tight">
    <tbody>
      <tr class="subtotal"><td>Balance per bank statement</td><td class="right mono"><?= money_c($st['closing_balance']) ?></td></tr>
      <tr><td>Add: outstanding entries in the ledger not yet on the statement</td><td class="right mono"><?= money_c($sum['unmatched_book_total']) ?></td></tr>
      <tr class="subtotal"><td>Adjusted bank balance</td><td class="right mono"><?= money_c((float) $st['closing_balance'] + (float) $sum['unmatched_book_total']) ?></td></tr>
      <tr><td colspan="2">&nbsp;</td></tr>
      <tr class="subtotal"><td>Balance per books (account <?= esc($bank['code']) ?>)</td><td class="right mono"><?= money_c($sum['book_balance']) ?></td></tr>
      <tr><td>Add: statement items not yet recorded in the books</td><td class="right mono"><?= money_c($sum['unmatched_stmt_total']) ?></td></tr>
      <tr class="subtotal"><td>Adjusted book balance</td><td class="right mono"><?= money_c((float) $sum['book_balance'] + (float) $sum['unmatched_stmt_total']) ?></td></tr>
      <tr><td colspan="2">&nbsp;</td></tr>
      <tr class="subtotal"><td><b>Difference</b></td><td class="right mono"><b><?= money_c($sum['difference']) ?></b></td></tr>
    </tbody>
  </table>
  <p class="muted small"><?= $sum['reconciled'] ? 'Reconciled — adjusted balances agree.' : 'Not reconciled — resolve the unmatched items until the difference is zero.' ?></p>
</div>

<div class="card">
  <h2>Unrecorded statement items (<?= $sum['counts']['stmt_unmatched'] ?>)</h2>
  <table class="grid tight">
    <thead><tr><th>Date</th><th>Description</th><th class="right">Amount</th></tr></thead>
    <tbody>
      <?php foreach ($sum['unmatched_stmt'] as $sl): ?>
        <tr><td class="nowrap"><?= date_id($sl['txn_date']) ?></td><td><?= esc($sl['description']) ?></td><td class="right mono"><?= money_c($sl['amount']) ?></td></tr>
      <?php endforeach ?>
      <?php if (! $sum['unmatched_stmt']): ?><tr><td colspan="3" class="muted">None.</td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h2>Outstanding book entries (<?= $sum['counts']['book_unmatched'] ?>)</h2>
  <table class="grid tight">
    <thead><tr><th>Date</th><th>Journal</th><th>Memo</th><th class="right">Amount</th></tr></thead>
    <tbody>
      <?php foreach ($sum['unmatched_book'] as $b): ?>
        <tr><td class="nowrap"><?= date_id($b['entry_date']) ?></td><td class="mono"><?= esc($b['journal_no']) ?></td><td><?= esc($b['memo'] ?: $b['jdesc']) ?></td><td class="right mono"><?= money_c($b['effect']) ?></td></tr>
      <?php endforeach ?>
      <?php if (! $sum['unmatched_book']): ?><tr><td colspan="4" class="muted">None.</td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
