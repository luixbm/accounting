<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$isDeposit = ($pay['kind'] ?? 'settlement') === 'deposit';
$cc        = $pay['currency_code'] ?: base_code();
$foreign   = $cc !== base_code();
?>

<div class="page-head">
  <div>
    <h1><?= esc($pay['receipt_no']) ?> <?= status_badge($pay['status'] === 'void' ? 'void' : 'posted') ?>
      <?php if ($isDeposit): ?><span class="badge badge-gray"><?= lang('Txn.dp_badge') ?></span><?php endif ?></h1>
    <div class="muted small">
      <?= esc($pay['customer_name']) ?> · <?= date_id($pay['receipt_date']) ?> · <?= lang('Txn.into_bank') ?>: <?= esc($pay['bank_name']) ?>
      · <?= esc($cc) ?><?= (float) $pay['exchange_rate'] != 1.0 ? ' @ ' . money($pay['exchange_rate'], 4) : '' ?>
    </div>
  </div>
  <div class="btn-group no-print">
    <?php if ($isDeposit && $pay['status'] === 'posted' && (float) $pay['unapplied'] > 0.005 && user_can('journal.post')): ?>
      <a class="btn" href="<?= site_url('sales/receipts/' . $pay['id'] . '/apply') ?>"><?= lang('Txn.apply_to_invoices') ?></a>
    <?php endif ?>
    <?php if ($pay['journal_id']): ?><a class="btn ghost" href="<?= site_url('journals/' . $pay['journal_id']) ?>"><?= lang('Txn.journal') ?></a><?php endif ?>
    <button class="btn secondary" onclick="window.print()"><?= lang('App.print') ?></button>
    <a class="btn ghost" href="<?= site_url('sales/receipts') ?>"><?= lang('App.back') ?></a>
  </div>
</div>

<?php if ($isDeposit): ?>
  <div class="row">
    <div class="card" style="flex:1;min-width:240px">
      <table class="grid tight">
        <tbody>
          <tr><td class="muted"><?= lang('Txn.dp_amount') ?></td><td class="right mono"><?= money($pay['amount']) ?> <?= esc($cc) ?></td></tr>
          <tr><td class="muted"><?= lang('Txn.applied') ?></td><td class="right mono"><?= money((float) $pay['amount'] - (float) $pay['unapplied']) ?></td></tr>
          <tr class="subtotal"><td><?= lang('Txn.unapplied') ?></td><td class="right mono"><?= money($pay['unapplied']) ?> <?= esc($cc) ?></td></tr>
        </tbody>
      </table>
      <?php if ($pay['reference']): ?><p class="small muted"><?= lang('Txn.reference_prefix', [esc($pay['reference'])]) ?></p><?php endif ?>
    </div>
    <div class="card" style="flex:2;min-width:340px">
      <h2><?= lang('Txn.applications_h') ?></h2>
      <?php if (! $applications): ?>
        <p class="muted small"><?= lang('Txn.not_applied_yet') ?></p>
      <?php else: ?>
        <?php foreach ($applications as $ap): ?>
          <table class="grid tight" style="margin-bottom:10px">
            <thead><tr><th><?= date_id($ap['date']) ?></th><th><?= lang('Report.col_ref') ?></th><th class="right"><?= esc($cc) ?></th><th class="no-print"></th></tr></thead>
            <tbody>
              <?php foreach ($ap['lines'] as $l): ?>
                <tr>
                  <td class="mono"><a href="<?= site_url('sales/' . $l['invoice_id']) ?>"><?= esc($l['internal_no']) ?></a></td>
                  <td class="small"><?= esc($l['customer_ref']) ?></td>
                  <td class="right mono"><?= money($l['amount']) ?></td>
                  <td class="no-print"></td>
                </tr>
              <?php endforeach ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="2" class="right"><?= lang('Txn.subtotal') ?></td>
                <td class="right mono"><?= money($ap['total']) ?></td>
                <td class="right no-print">
                  <?php if ($pay['status'] === 'posted' && user_can('journal.void')): ?>
                    <form method="post" action="<?= site_url('sales/receipts/' . $pay['id'] . '/unapply') ?>" onsubmit="return confirm('<?= esc(lang('Txn.unapply_confirm'), 'js') ?>')">
                      <?= csrf_field() ?><input type="hidden" name="journal_id" value="<?= $ap['journal_id'] ?>">
                      <button class="btn sm ghost"><?= lang('Txn.unapply') ?></button>
                    </form>
                  <?php endif ?>
                </td>
              </tr>
            </tfoot>
          </table>
        <?php endforeach ?>
      <?php endif ?>
    </div>
  </div>
<?php else: ?>
  <div class="card" style="max-width:560px">
    <table class="grid tight">
      <thead><tr><th><?= lang('Txn.invoice') ?></th><th><?= lang('Txn.customer_ref') ?></th><th class="right"><?= lang('Txn.applied') ?> (<?= esc($cc) ?>)</th><?php if ($foreign): ?><th class="right"><?= lang('Txn.at_inv_rate', [base_code()]) ?></th><?php endif ?></tr></thead>
      <tbody>
        <?php foreach ($allocs as $a): ?>
          <tr>
            <td class="mono"><a href="<?= site_url('sales/' . $a['invoice_id']) ?>"><?= esc($a['internal_no']) ?></a></td>
            <td class="small"><?= esc($a['customer_ref']) ?></td>
            <td class="right mono"><?= money($a['amount']) ?></td>
            <?php if ($foreign): ?><td class="right mono"><?= money($a['amount_base']) ?></td><?php endif ?>
          </tr>
        <?php endforeach ?>
      </tbody>
      <tfoot>
        <tr><td colspan="2" class="right"><?= lang('Txn.cash_received') ?></td><td class="right mono"><?= money($pay['amount']) ?></td><?php if ($foreign): ?><td class="right mono"><?= money($pay['amount_base']) ?></td><?php endif ?></tr>
        <?php if ($foreign): ?><tr class="muted small"><td colspan="4" class="right"><?= lang('Txn.fx_diff_note') ?></td></tr><?php endif ?>
      </tfoot>
    </table>
    <?php if ($pay['reference']): ?><p class="small muted"><?= lang('Txn.reference_prefix', [esc($pay['reference'])]) ?></p><?php endif ?>
  </div>
<?php endif ?>

<?php if ($pay['status'] === 'posted' && user_can('journal.void')): ?>
  <div class="card">
    <h2><?= $isDeposit ? lang('Txn.void_deposit') : lang('Txn.void_receipt') ?></h2>
    <form method="post" action="<?= site_url('sales/receipts/' . $pay['id'] . '/void') ?>" class="inline"
      onsubmit="return confirm('<?= esc($isDeposit ? lang('Txn.void_dep_confirm') : lang('Txn.void_rcpt_confirm'), 'js') ?>')">
      <?= csrf_field() ?>
      <input name="reason" placeholder="<?= esc(lang('Txn.reason'), 'attr') ?>" style="max-width:320px">
      <button class="btn danger" type="submit"><?= lang('Txn.void_reverse') ?></button>
    </form>
  </div>
<?php endif ?>

<?= $this->endSection() ?>
