<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$foreign     = strtoupper((string) ($inv['currency_code'] ?? base_code())) !== base_code();
$outstanding = (float) $inv['total_base'] - (float) $inv['received_base'];
$statusLabel = ['draft' => 'draft', 'posted' => 'posted', 'partial' => 'draft', 'paid' => 'posted', 'void' => 'void'][$inv['status']] ?? 'draft';
?>

<div class="page-head">
  <div>
    <h1><?= esc($inv['internal_no']) ?> <?= status_badge($statusLabel) ?>
      <?php if ($inv['status'] === 'partial'): ?><span class="badge badge-gray">partial</span><?php endif ?>
      <?php if ($inv['status'] === 'paid'): ?><span class="badge badge-green">paid</span><?php endif ?>
    </h1>
    <div class="muted small">
      <?= esc($inv['customer_name']) ?> · <?= date_id($inv['invoice_date']) ?>
      <?php if ($inv['due_date']): ?> · due <?= date_id($inv['due_date']) ?><?php endif ?>
      · <?= esc($inv['currency_code']) ?><?= $foreign ? ' @ ' . money($inv['exchange_rate'], 4) : '' ?>
      <?php if ($inv['customer_ref']): ?> · ref <?= esc($inv['customer_ref']) ?><?php endif ?>
    </div>
  </div>
  <div class="btn-group no-print">
    <?php $unpaidPosted = $inv['status'] === 'posted' && (float) $inv['received_base'] <= 0.005; ?>
    <?php if ($inv['status'] === 'draft'): ?>
      <?php if (user_can('journal.create')): ?><a class="btn ghost" href="<?= site_url('sales/' . $inv['id'] . '/edit') ?>">Edit</a><?php endif ?>
      <?php if (user_can('journal.post')): ?>
        <form method="post" action="<?= site_url('sales/' . $inv['id'] . '/post') ?>" onsubmit="return confirm('Post this invoice?')">
          <?= csrf_field() ?><button class="btn" type="submit">Post</button>
        </form>
      <?php endif ?>
      <?php if (user_can('journal.delete')): ?>
        <form method="post" action="<?= site_url('sales/' . $inv['id'] . '/delete') ?>" onsubmit="return confirm('Delete this draft?')">
          <?= csrf_field() ?><button class="btn danger" type="submit">Delete</button>
        </form>
      <?php endif ?>
    <?php elseif ($unpaidPosted): ?>
      <?php if (user_can('journal.create') && user_can('journal.void')): ?>
        <a class="btn ghost" href="<?= site_url('sales/' . $inv['id'] . '/edit') ?>">Edit</a>
      <?php endif ?>
      <?php if (user_can('journal.delete') && user_can('journal.void')): ?>
        <form method="post" action="<?= site_url('sales/' . $inv['id'] . '/delete') ?>" onsubmit="return confirm('Delete this posted invoice? Its ledger journal is removed (no reversing entry).')">
          <?= csrf_field() ?><button class="btn danger" type="submit">Delete</button>
        </form>
      <?php endif ?>
    <?php endif ?>
    <?php if (in_array($inv['status'], ['posted', 'partial'], true) && user_can('journal.post')): ?>
      <a class="btn" href="<?= site_url('sales/receipts/new?customer_id=' . $inv['customer_id']) ?>">Receive</a>
    <?php endif ?>
    <?php if ($inv['journal_id']): ?><a class="btn ghost" href="<?= site_url('journals/' . $inv['journal_id']) ?>">Journal</a><?php endif ?>
    <button class="btn secondary" onclick="window.print()">Print</button>
    <a class="btn ghost" href="<?= site_url('sales') ?>">Back</a>
  </div>
</div>

<?php
$hasDetail = false;
foreach ($lines as $l) {
    if (! empty($l['service_date']) || ! empty($l['party_name'])
        || ! empty($l['units']) || ! empty($l['pax']) || ! empty($l['duration'])) {
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
        <th>Account</th><th style="font-family:sans-serif">Description</th><th>Job</th>
        <?php if ($hasDetail): ?><th>Service</th><th>Booking name</th><?php endif ?>
        <th class="right">Amount<?= $foreign ? ' (' . esc($inv['currency_code']) . ')' : '' ?></th>
      </tr></thead>
      <tbody>
        <?php foreach ($lines as $l): ?>
          <tr>
            <td class="nowrap"><?= esc($l['account_code'] . ' · ' . $l['account_name']) ?></td>
            <td style="font-family:sans-serif">
              <?= esc($l['description']) ?>
              <?php if (! empty($l['remark'])): ?><br><span class="muted small">✎ <?= esc($l['remark']) ?></span><?php endif ?>
            </td>
            <td class="small"><?= esc($l['job_code']) ?></td>
            <?php if ($hasDetail): ?>
              <td class="small nowrap"><?= $l['service_date'] ? date_id($l['service_date']) : '' ?></td>
              <td class="small" style="font-family:sans-serif">
                <?= esc($l['party_name']) ?>
                <?php
                    $bits = array_filter([
                        ! empty($l['pax']) ? $l['pax'] . ' pax' : '',
                        ! empty($l['duration']) ? $l['duration'] . ' days' : '',
                        trim($l['nights'] . ' ' . $l['units']),
                    ]);
                ?>
                <?php if ($bits): ?><br><span class="muted"><?= esc(implode(' · ', $bits)) ?></span><?php endif ?>
              </td>
            <?php endif ?>
            <td class="right"><?= money($l['amount']) ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
      <tfoot>
        <tr><td colspan="<?= $foot ?>" class="right">Subtotal</td><td class="right"><?= money($inv['subtotal']) ?></td></tr>
        <?php if ((float) $inv['ppn_amount'] > 0): ?><tr><td colspan="<?= $foot ?>" class="right">PPN Keluaran</td><td class="right"><?= money($inv['ppn_amount']) ?></td></tr><?php endif ?>
        <?php if ((float) $inv['pph_amount'] > 0): ?><tr><td colspan="<?= $foot ?>" class="right">PPh 23 dipotong pelanggan</td><td class="right">(<?= money($inv['pph_amount']) ?>)</td></tr><?php endif ?>
        <tr><td colspan="<?= $foot ?>" class="right">Receivable</td><td class="right"><?= money($inv['total']) ?></td></tr>
      </tfoot>
    </table>
    </div>
  </div>

  <div class="card" style="flex:1;min-width:240px">
    <h2>Receipts</h2>
    <table class="grid tight">
      <tbody>
        <tr><td class="muted">Total (<?= base_code() ?>)</td><td class="right mono"><?= money($inv['total_base']) ?></td></tr>
        <tr><td class="muted">Paid</td><td class="right mono"><?= money($inv['received_base']) ?></td></tr>
        <tr class="subtotal"><td>Outstanding</td><td class="right mono"><?= money($outstanding) ?></td></tr>
      </tbody>
    </table>
    <?php if ($allocs): ?>
      <table class="grid tight" style="margin-top:10px">
        <thead><tr><th>Receipt</th><th>Date</th><th class="right">Amount</th></tr></thead>
        <tbody>
          <?php foreach ($allocs as $a): ?>
            <tr>
              <td><a href="<?= site_url('sales/receipts/' . $a['receipt_id']) ?>"><?= esc($a['receipt_no']) ?></a></td>
              <td class="nowrap small"><?= date_id($a['receipt_date']) ?></td>
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
    <h2>Additional information</h2>
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
    <h2>Void this invoice</h2>
    <form method="post" action="<?= site_url('sales/' . $inv['id'] . '/void') ?>" class="inline"
      onsubmit="return confirm('Void this invoice? Its journal will be reversed.')">
      <?= csrf_field() ?>
      <input name="reason" placeholder="Reason" required style="max-width:340px">
      <button class="btn danger" type="submit">Void</button>
      <span class="muted small">Void any payments first.</span>
    </form>
  </div>
<?php endif ?>

<?= $this->endSection() ?>
