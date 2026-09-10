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
      <?php if (($inv['doc_type'] ?? '') === 'credit_note'): ?><span class="badge badge-amber"><?= lang('Txn.doc_credit_note') ?></span><?php endif ?>
      <?php if ($inv['status'] === 'partial'): ?><span class="badge badge-gray"><?= lang('App.partial') ?></span><?php endif ?>
      <?php if ($inv['status'] === 'paid'): ?><span class="badge badge-green"><?= lang('App.paid') ?></span><?php endif ?>
    </h1>
    <div class="muted small">
      <?= esc($inv['customer_name']) ?> · <?= date_id($inv['invoice_date']) ?>
      <?php if ($inv['due_date']): ?> · <?= lang('Txn.due_prefix', [date_id($inv['due_date'])]) ?><?php endif ?>
      · <?= esc($inv['currency_code']) ?><?= $foreign ? ' @ ' . money($inv['exchange_rate'], 4) : '' ?>
      <?php if ($inv['customer_ref']): ?> · <?= lang('Txn.ref_prefix', [esc($inv['customer_ref'])]) ?><?php endif ?>
    </div>
  </div>
  <div class="btn-group no-print">
    <?php $unpaidPosted = $inv['status'] === 'posted' && (float) $inv['received_base'] <= 0.005; ?>
    <?php if ($inv['status'] === 'draft'): ?>
      <?php if (user_can('journal.create')): ?><a class="btn ghost" href="<?= site_url('sales/' . $inv['id'] . '/edit') ?>"><?= lang('App.edit') ?></a><?php endif ?>
      <?php if (user_can('journal.post')): ?>
        <form method="post" action="<?= site_url('sales/' . $inv['id'] . '/post') ?>" onsubmit="return confirm('<?= esc(lang('Txn.post_invoice_confirm'), 'js') ?>')">
          <?= csrf_field() ?><button class="btn" type="submit"><?= lang('App.post') ?></button>
        </form>
      <?php endif ?>
      <?php if (user_can('journal.delete')): ?>
        <form method="post" action="<?= site_url('sales/' . $inv['id'] . '/delete') ?>" onsubmit="return confirm('<?= esc(lang('Txn.delete_draft_confirm'), 'js') ?>')">
          <?= csrf_field() ?><button class="btn danger" type="submit"><?= lang('App.delete') ?></button>
        </form>
      <?php endif ?>
    <?php elseif ($unpaidPosted): ?>
      <?php if (user_can('journal.create') && user_can('journal.void')): ?>
        <a class="btn ghost" href="<?= site_url('sales/' . $inv['id'] . '/edit') ?>"><?= lang('App.edit') ?></a>
      <?php endif ?>
      <?php if (user_can('journal.delete') && user_can('journal.void')): ?>
        <form method="post" action="<?= site_url('sales/' . $inv['id'] . '/delete') ?>" onsubmit="return confirm('<?= esc(lang('Txn.delete_posted_confirm'), 'js') ?>')">
          <?= csrf_field() ?><button class="btn danger" type="submit"><?= lang('App.delete') ?></button>
        </form>
      <?php endif ?>
    <?php endif ?>
    <?php if (in_array($inv['status'], ['posted', 'partial'], true) && user_can('journal.post')): ?>
      <a class="btn" href="<?= site_url('sales/receipts/new?customer_id=' . $inv['customer_id']) ?>"><?= lang('Txn.receive') ?></a>
    <?php endif ?>
    <?php if ($inv['journal_id']): ?><a class="btn ghost" href="<?= site_url('journals/' . $inv['journal_id']) ?>"><?= lang('Txn.journal') ?></a><?php endif ?>
    <button class="btn secondary" onclick="window.print()"><?= lang('App.print') ?></button>
    <a class="btn ghost" href="<?= site_url('sales') ?>"><?= lang('App.back') ?></a>
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
        <th><?= lang('App.account') ?></th><th style="font-family:sans-serif"><?= lang('App.description') ?></th><th><?= lang('Txn.job') ?></th>
        <?php if ($hasDetail): ?><th><?= lang('Txn.service') ?></th><th><?= lang('Txn.booking_name') ?></th><?php endif ?>
        <th class="right"><?= lang('App.amount') ?><?= $foreign ? ' (' . esc($inv['currency_code']) . ')' : '' ?></th>
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
                        ! empty($l['pax']) ? lang('Txn.pax_suffix', [$l['pax']]) : '',
                        ! empty($l['duration']) ? lang('Txn.days_suffix', [$l['duration']]) : '',
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
        <tr><td colspan="<?= $foot ?>" class="right"><?= lang('Txn.subtotal') ?></td><td class="right"><?= money($inv['subtotal']) ?></td></tr>
        <?php if ((float) $inv['ppn_amount'] > 0): ?><tr><td colspan="<?= $foot ?>" class="right"><?= lang('Txn.ppn_out') ?></td><td class="right"><?= money($inv['ppn_amount']) ?></td></tr><?php endif ?>
        <?php if ((float) $inv['pph_amount'] > 0): ?><tr><td colspan="<?= $foot ?>" class="right"><?= lang('Txn.pph_deducted_cust') ?></td><td class="right">(<?= money($inv['pph_amount']) ?>)</td></tr><?php endif ?>
        <tr><td colspan="<?= $foot ?>" class="right"><?= lang('Txn.receivable') ?></td><td class="right"><?= money($inv['total']) ?></td></tr>
      </tfoot>
    </table>
    </div>
  </div>

  <div class="card" style="flex:1;min-width:240px">
    <h2><?= lang('Txn.receipts_h') ?></h2>
    <table class="grid tight">
      <tbody>
        <tr><td class="muted"><?= lang('App.total') ?> (<?= base_code() ?>)</td><td class="right mono"><?= money($inv['total_base']) ?></td></tr>
        <tr><td class="muted"><?= lang('Txn.paid') ?></td><td class="right mono"><?= money($inv['received_base']) ?></td></tr>
        <tr class="subtotal"><td><?= lang('Txn.outstanding') ?></td><td class="right mono"><?= money($outstanding) ?></td></tr>
      </tbody>
    </table>
    <?php if ($allocs): ?>
      <table class="grid tight" style="margin-top:10px">
        <thead><tr><th><?= lang('Txn.receipt') ?></th><th><?= lang('App.date') ?></th><th class="right"><?= lang('App.amount') ?></th></tr></thead>
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

<?php if (! empty($eiEnabled)): ?>
  <?php
  $eiSt   = $inv['einvoice_status'] ?? null;
  $posted = in_array($inv['status'], ['posted', 'partial', 'paid'], true);
  $portal = ($eiEnv ?? 'sandbox') === 'production' ? 'https://myinvois.hasil.gov.my' : 'https://preprod.myinvois.hasil.gov.my';
  $shareUrl = ($eiSt === 'valid' && ! empty($inv['einvoice_uuid']) && ! empty($inv['einvoice_long_id']))
      ? $portal . '/' . rawurlencode($inv['einvoice_uuid']) . '/share/' . rawurlencode($inv['einvoice_long_id'])
      : null;
  $badge = ['submitted' => 'badge-gray', 'valid' => 'badge-green', 'invalid' => 'badge-red', 'cancelled' => 'badge-amber'][$eiSt] ?? 'badge-gray';
  $stLabel = ['submitted' => lang('Einvoice.ei_st_submitted'), 'valid' => lang('Einvoice.ei_st_valid'), 'invalid' => lang('Einvoice.ei_st_invalid'), 'cancelled' => lang('Einvoice.ei_st_cancelled')][$eiSt] ?? ucfirst((string) $eiSt);
  ?>
  <div class="card no-print" style="max-width:560px">
    <div class="announce-card-head">
      <h2 style="margin:0"><?= lang('Einvoice.ei_panel_h') ?></h2>
      <?php if ($eiSt): ?><span class="badge <?= $badge ?>"><?= esc($stLabel) ?></span><?php endif ?>
    </div>

    <?php if (! $posted): ?>
      <p class="muted small"><?= lang('Einvoice.ei_post_first') ?></p>

    <?php elseif ($eiSt === null || $eiSt === 'invalid'): ?>
      <?php if ($eiSt === 'invalid' && ! empty($inv['einvoice_error'])): ?>
        <div class="alert alert-error small" style="white-space:pre-wrap"><?= esc(mb_substr((string) $inv['einvoice_error'], 0, 800)) ?></div>
      <?php endif ?>
      <form method="post" action="<?= site_url('sales/' . $inv['id'] . '/einvoice/submit') ?>"
        onsubmit="return confirm('<?= esc(lang('Einvoice.ei_submit_confirm', [$eiEnv]), 'js') ?>')">
        <?= csrf_field() ?>
        <button class="btn" type="submit"><?= $eiSt === 'invalid' ? lang('Einvoice.ei_resubmit') : lang('Einvoice.ei_submit') ?></button>
        <span class="muted small"><?= lang('Einvoice.ei_env_note', [esc($eiEnv)]) ?></span>
      </form>

    <?php elseif ($eiSt === 'submitted'): ?>
      <p class="small"><?= lang('Einvoice.ei_awaiting', [date_id(substr((string) $inv['einvoice_submitted_at'], 0, 10))]) ?></p>
      <p class="muted small mono"><?= esc($inv['einvoice_submission_uid']) ?></p>
      <form method="post" action="<?= site_url('sales/' . $inv['id'] . '/einvoice/status') ?>" class="inline">
        <?= csrf_field() ?>
        <button class="btn ghost" type="submit"><?= lang('Einvoice.ei_check') ?></button>
      </form>

    <?php elseif ($eiSt === 'valid'): ?>
      <p class="small"><?= lang('Einvoice.ei_validated', [date_id(substr((string) $inv['einvoice_validated_at'], 0, 10))]) ?></p>
      <?php if ($shareUrl): ?>
        <p><a class="btn ghost sm" href="<?= esc($shareUrl) ?>" target="_blank" rel="noopener"><?= lang('Einvoice.ei_view_lhdn') ?> &nearr;</a></p>
      <?php endif ?>
      <p class="muted small mono"><?= esc($inv['einvoice_uuid']) ?></p>

    <?php elseif ($eiSt === 'cancelled'): ?>
      <p class="muted small"><?= lang('Einvoice.ei_is_cancelled') ?></p>
    <?php endif ?>
  </div>
<?php endif ?>

<?php if (in_array($inv['status'], ['posted', 'partial'], true) && user_can('journal.void')): ?>
  <div class="card">
    <h2><?= lang('Txn.void_invoice') ?></h2>
    <form method="post" action="<?= site_url('sales/' . $inv['id'] . '/void') ?>" class="inline"
      onsubmit="return confirm('<?= esc(lang('Txn.void_invoice_confirm'), 'js') ?>')">
      <?= csrf_field() ?>
      <input name="reason" placeholder="<?= esc(lang('Txn.reason'), 'attr') ?>" required style="max-width:340px">
      <button class="btn danger" type="submit"><?= lang('App.void') ?></button>
      <span class="muted small"><?= lang('Txn.void_payments_first') ?></span>
    </form>
  </div>
<?php endif ?>

<?= $this->endSection() ?>
