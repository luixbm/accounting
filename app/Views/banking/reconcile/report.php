<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
/** @var array $st @var array $bank @var array $sum */
?>
<div class="page-head no-print">
  <div><h1><?= lang('Import.bk_rep_h') ?></h1></div>
  <div class="btn-group">
    <a class="btn ghost" href="<?= site_url('banking/reconcile/' . $st['id']) ?>">&lsaquo; <?= lang('App.back') ?></a>
    <button class="btn" type="button" onclick="window.print()"><?= lang('App.print') ?></button>
  </div>
</div>

<div class="report-title">
  <?php if ($u = company_logo_url()): ?><img src="<?= esc($u) ?>"><?php endif ?>
  <div class="co"><?= esc(company_legal_name()) ?></div>
  <h1><?= lang('Import.bk_rep_title', [esc($bank['code'] . ' ' . $bank['name'])]) ?></h1>
  <div class="muted"><?= lang('Import.bk_rep_as_of', [date_id($st['statement_date'])]) ?><?= $st['status'] === 'reconciled' ? lang('Import.bk_rep_reconciled') : lang('Import.bk_rep_draft') ?></div>
</div>

<div class="card">
  <table class="grid tight">
    <tbody>
      <tr class="subtotal"><td><?= lang('Import.bk_rep_bal_bank') ?></td><td class="right mono"><?= money_c($st['closing_balance']) ?></td></tr>
      <tr><td><?= lang('Import.bk_rep_add_book') ?></td><td class="right mono"><?= money_c($sum['unmatched_book_total']) ?></td></tr>
      <tr class="subtotal"><td><?= lang('Import.bk_rep_adj_bank') ?></td><td class="right mono"><?= money_c((float) $st['closing_balance'] + (float) $sum['unmatched_book_total']) ?></td></tr>
      <tr><td colspan="2">&nbsp;</td></tr>
      <tr class="subtotal"><td><?= lang('Import.bk_rep_bal_books', [esc($bank['code'])]) ?></td><td class="right mono"><?= money_c($sum['book_balance']) ?></td></tr>
      <tr><td><?= lang('Import.bk_rep_add_stmt') ?></td><td class="right mono"><?= money_c($sum['unmatched_stmt_total']) ?></td></tr>
      <tr class="subtotal"><td><?= lang('Import.bk_rep_adj_books') ?></td><td class="right mono"><?= money_c((float) $sum['book_balance'] + (float) $sum['unmatched_stmt_total']) ?></td></tr>
      <tr><td colspan="2">&nbsp;</td></tr>
      <tr class="subtotal"><td><b><?= lang('Import.bk_rep_difference') ?></b></td><td class="right mono"><b><?= money_c($sum['difference']) ?></b></td></tr>
    </tbody>
  </table>
  <p class="muted small"><?= $sum['reconciled'] ? lang('Import.bk_rep_ok') : lang('Import.bk_rep_bad') ?></p>
</div>

<div class="card">
  <h2><?= lang('Import.bk_rep_unrec_h', [$sum['counts']['stmt_unmatched']]) ?></h2>
  <table class="grid tight">
    <thead><tr><th><?= lang('App.date') ?></th><th><?= lang('App.description') ?></th><th class="right"><?= lang('App.amount') ?></th></tr></thead>
    <tbody>
      <?php foreach ($sum['unmatched_stmt'] as $sl): ?>
        <tr><td class="nowrap"><?= date_id($sl['txn_date']) ?></td><td><?= esc($sl['description']) ?></td><td class="right mono"><?= money_c($sl['amount']) ?></td></tr>
      <?php endforeach ?>
      <?php if (! $sum['unmatched_stmt']): ?><tr><td colspan="3" class="muted"><?= lang('Import.bk_rep_none') ?></td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h2><?= lang('Import.bk_rep_out_h', [$sum['counts']['book_unmatched']]) ?></h2>
  <table class="grid tight">
    <thead><tr><th><?= lang('App.date') ?></th><th><?= lang('Import.bk_c_journal') ?></th><th><?= lang('Import.bk_c_memo') ?></th><th class="right"><?= lang('App.amount') ?></th></tr></thead>
    <tbody>
      <?php foreach ($sum['unmatched_book'] as $b): ?>
        <tr><td class="nowrap"><?= date_id($b['entry_date']) ?></td><td class="mono"><?= esc($b['journal_no']) ?></td><td><?= esc($b['memo'] ?: $b['jdesc']) ?></td><td class="right mono"><?= money_c($b['effect']) ?></td></tr>
      <?php endforeach ?>
      <?php if (! $sum['unmatched_book']): ?><tr><td colspan="4" class="muted"><?= lang('Import.bk_rep_none') ?></td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
