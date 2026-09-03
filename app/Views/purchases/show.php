<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$foreign     = strtoupper((string) ($inv['currency_code'] ?? base_code())) !== base_code();
$outstanding = (float) $inv['total_base'] - (float) $inv['paid_base'];
$statusLabel = ['draft' => 'draft', 'posted' => 'posted', 'partial' => 'draft', 'paid' => 'posted', 'void' => 'void'][$inv['status']] ?? 'draft';
?>

<div class="page-head">
  <div>
    <h1><?= esc($inv['internal_no']) ?> <?= status_badge($statusLabel) ?>
      <?php if ($inv['status'] === 'partial'): ?><span class="badge badge-gray"><?= lang('App.partial') ?></span><?php endif ?>
      <?php if ($inv['status'] === 'paid'): ?><span class="badge badge-green"><?= lang('App.paid') ?></span><?php endif ?>
    </h1>
    <div class="muted small">
      <?= esc($inv['supplier_name']) ?> · <?= date_id($inv['invoice_date']) ?>
      <?php if ($inv['due_date']): ?> · <?= lang('Txn.due_prefix', [date_id($inv['due_date'])]) ?><?php endif ?>
      · <?= esc($inv['currency_code']) ?><?= $foreign ? ' @ ' . money($inv['exchange_rate'], 4) : '' ?>
      <?php if ($inv['supplier_ref']): ?> · <?= lang('Txn.ref_prefix', [esc($inv['supplier_ref'])]) ?><?php endif ?>
    </div>
  </div>
  <div class="btn-group no-print">
    <?php $unpaidPosted = $inv['status'] === 'posted' && (float) $inv['paid_base'] <= 0.005; ?>
    <?php if ($inv['status'] === 'draft'): ?>
      <?php if (user_can('journal.create')): ?><a class="btn ghost" href="<?= site_url('purchases/' . $inv['id'] . '/edit') ?>"><?= lang('App.edit') ?></a><?php endif ?>
      <?php if (user_can('journal.post')): ?>
        <form method="post" action="<?= site_url('purchases/' . $inv['id'] . '/post') ?>" onsubmit="return confirm('<?= esc(lang('Txn.post_invoice_confirm'), 'js') ?>')">
          <?= csrf_field() ?><button class="btn" type="submit"><?= lang('App.post') ?></button>
        </form>
      <?php endif ?>
      <?php if (user_can('journal.delete')): ?>
        <form method="post" action="<?= site_url('purchases/' . $inv['id'] . '/delete') ?>" onsubmit="return confirm('<?= esc(lang('Txn.delete_draft_confirm'), 'js') ?>')">
          <?= csrf_field() ?><button class="btn danger" type="submit"><?= lang('App.delete') ?></button>
        </form>
      <?php endif ?>
    <?php elseif ($unpaidPosted): ?>
      <?php if (user_can('journal.create') && user_can('journal.void')): ?>
        <a class="btn ghost" href="<?= site_url('purchases/' . $inv['id'] . '/edit') ?>"><?= lang('App.edit') ?></a>
      <?php endif ?>
      <?php if (user_can('journal.delete') && user_can('journal.void')): ?>
        <form method="post" action="<?= site_url('purchases/' . $inv['id'] . '/delete') ?>" onsubmit="return confirm('<?= esc(lang('Txn.delete_posted_confirm'), 'js') ?>')">
          <?= csrf_field() ?><button class="btn danger" type="submit"><?= lang('App.delete') ?></button>
        </form>
      <?php endif ?>
    <?php endif ?>
    <?php if (in_array($inv['status'], ['posted', 'partial'], true) && user_can('journal.post')): ?>
      <a class="btn" href="<?= site_url('purchases/payments/new?supplier_id=' . $inv['supplier_id']) ?>"><?= lang('Txn.pay') ?></a>
    <?php endif ?>
    <?php if ($inv['journal_id']): ?><a class="btn ghost" href="<?= site_url('journals/' . $inv['journal_id']) ?>"><?= lang('Txn.journal') ?></a><?php endif ?>
    <button class="btn secondary" onclick="window.print()"><?= lang('App.print') ?></button>
    <a class="btn ghost" href="<?= site_url('purchases') ?>"><?= lang('App.back') ?></a>
  </div>
</div>

<?php
$hasDetail = false;
foreach ($lines as $l) {
    if (! empty($l['service_date']) || ! empty($l['booking_ref']) || ! empty($l['party_name']) || ! empty($l['units'])) {
        $hasDetail = true;
        break;
    }
}
$foot = $hasDetail ? 5 : 3;
?>
<div class="row">
  <div class="card" style="flex:2;min-width:340px">
    <div style="overflow-x:auto">
    <table class="grid tight mono">
      <thead><tr>
        <th><?= lang('App.account') ?></th><th style="font-family:sans-serif"><?= lang('App.description') ?></th><th><?= lang('Txn.job') ?></th>
        <?php if ($hasDetail): ?><th><?= lang('Txn.service') ?></th><th><?= lang('Txn.booking') ?></th><?php endif ?>
        <th class="right"><?= lang('App.amount') ?><?= $foreign ? ' (' . esc($inv['currency_code']) . ')' : '' ?></th>
      </tr></thead>
      <tbody>
        <?php foreach ($lines as $l): ?>
          <tr>
            <td class="nowrap"><?= esc($l['account_code'] . ' · ' . $l['account_name']) ?></td>
            <td style="font-family:sans-serif">
              <?= esc($l['description']) ?>
              <?php if (! empty($l['cost_remark'])): ?><br><span class="muted small">✎ <?= esc($l['cost_remark']) ?></span><?php endif ?>
            </td>
            <td class="small"><?= esc($l['job_code']) ?></td>
            <?php if ($hasDetail): ?>
              <td class="small nowrap"><?= $l['service_date'] ? date_id($l['service_date']) : '' ?></td>
              <td class="small" style="font-family:sans-serif">
                <?= esc($l['booking_ref']) ?>
                <?php if (! empty($l['party_name'])): ?><br><span class="muted"><?= esc($l['party_name']) ?></span><?php endif ?>
                <?php if (! empty($l['units'])): ?><br><span class="muted"><?= esc(trim($l['nights'] . ' ' . $l['units'])) ?></span><?php endif ?>
              </td>
            <?php endif ?>
            <td class="right">
              <?= money($l['amount']) ?><?= $l['amount'] == 0 && ($l['cost_source'] ?? '') !== 'actual' ? ' <span class="badge badge-gray" style="font-size:.7em">' . esc(lang('Txn.no_cost_yet')) . '</span>' : '' ?>
              <?php
                $bud = $l['budget_amount'] ?? null;
                if ($bud !== null && abs((float) $bud - (float) $l['amount']) >= 0.005):
                    $delta = (float) $l['amount'] - (float) $bud;
              ?>
                <div class="small muted"><?= lang('Txn.budget_prefix', [money($bud)]) ?>
                  · <span style="color:var(--<?= $delta > 0 ? 'red' : 'green' ?>)"><?= ($delta > 0 ? '+' : '') . money($delta) ?></span>
                </div>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
      <tfoot>
        <tr><td colspan="<?= $foot ?>" class="right"><?= lang('Txn.subtotal') ?></td><td class="right"><?= money($inv['subtotal']) ?></td></tr>
        <?php if ((float) $inv['ppn_amount'] > 0): ?><tr><td colspan="<?= $foot ?>" class="right"><?= lang('Txn.ppn_in') ?></td><td class="right"><?= money($inv['ppn_amount']) ?></td></tr><?php endif ?>
        <?php if ((float) $inv['pph_amount'] > 0): ?><tr><td colspan="<?= $foot ?>" class="right"><?= lang('Txn.pph_deducted') ?></td><td class="right">(<?= money($inv['pph_amount']) ?>)</td></tr><?php endif ?>
        <tr><td colspan="<?= $foot ?>" class="right"><?= lang('Txn.payable') ?></td><td class="right"><?= money($inv['total']) ?></td></tr>
      </tfoot>
    </table>
    </div>
  </div>

  <div class="card" style="flex:1;min-width:240px">
    <h2><?= lang('Txn.payment') ?></h2>
    <table class="grid tight">
      <tbody>
        <tr><td class="muted"><?= lang('App.total') ?> (<?= base_code() ?>)</td><td class="right mono"><?= money($inv['total_base']) ?></td></tr>
        <tr><td class="muted"><?= lang('Txn.paid') ?></td><td class="right mono"><?= money($inv['paid_base']) ?></td></tr>
        <tr class="subtotal"><td><?= lang('Txn.outstanding') ?></td><td class="right mono"><?= money($outstanding) ?></td></tr>
      </tbody>
    </table>
    <?php if ($allocs): ?>
      <table class="grid tight" style="margin-top:10px">
        <thead><tr><th><?= lang('Txn.payment') ?></th><th><?= lang('App.date') ?></th><th class="right"><?= lang('App.amount') ?></th></tr></thead>
        <tbody>
          <?php foreach ($allocs as $a): ?>
            <tr>
              <td><a href="<?= site_url('purchases/payments/' . $a['payment_id']) ?>"><?= esc($a['payment_no']) ?></a></td>
              <td class="nowrap small"><?= date_id($a['payment_date']) ?></td>
              <td class="right mono"><?= money($a['amount_base']) ?></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    <?php endif ?>
  </div>
</div>

<?php if ($cfDefs && array_filter($cfValues)): ?>
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

<?php if (in_array($inv['status'], ['posted', 'partial'], true) && user_can('journal.void')): ?>
  <div class="card">
    <h2><?= lang('Txn.void_invoice') ?></h2>
    <form method="post" action="<?= site_url('purchases/' . $inv['id'] . '/void') ?>" class="inline"
      onsubmit="return confirm('<?= esc(lang('Txn.void_invoice_confirm'), 'js') ?>')">
      <?= csrf_field() ?>
      <input name="reason" placeholder="<?= esc(lang('Txn.reason'), 'attr') ?>" required style="max-width:340px">
      <button class="btn danger" type="submit"><?= lang('App.void') ?></button>
      <span class="muted small"><?= lang('Txn.void_payments_first') ?></span>
    </form>
  </div>
<?php endif ?>

<?= $this->endSection() ?>
