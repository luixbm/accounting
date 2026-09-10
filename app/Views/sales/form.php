<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$isEdit = $inv !== null;
$action = $isEdit ? site_url('sales/' . $inv['id']) : site_url('sales');

$oldAcc = old('line_account');
if (is_array($oldAcc)) {
    $rows = [];
    foreach ($oldAcc as $i => $a) {
        $rows[] = [
            'account_id'   => $a,
            'job_id'       => old('line_job')[$i] ?? '',
            'description'  => old('line_desc')[$i] ?? '',
            'amount'       => old('line_amount')[$i] ?? '',
            'service_date' => old('line_service_date')[$i] ?? '',
            'units'        => old('line_units')[$i] ?? '',
            'party_name'   => old('line_party')[$i] ?? '',
            'booking_ref'  => old('line_booking')[$i] ?? '',
            'nights'       => old('line_nights')[$i] ?? '',
            'pax'          => old('line_pax')[$i] ?? '',
            'duration'     => old('line_duration')[$i] ?? '',
            'remark'       => old('line_remark')[$i] ?? '',
        ];
    }
} elseif ($lines) {
    $rows = array_map(static fn ($l) => [
        'account_id' => $l['account_id'], 'job_id' => $l['job_id'],
        'description' => $l['description'], 'amount' => $l['amount'],
        'service_date' => $l['service_date'] ?? '', 'units' => $l['units'] ?? '',
        'party_name' => $l['party_name'] ?? '', 'booking_ref' => $l['booking_ref'] ?? '', 'nights' => $l['nights'] ?? '',
        'pax' => $l['pax'] ?? '', 'duration' => $l['duration'] ?? '', 'remark' => $l['remark'] ?? '',
    ], $lines);
} else {
    $rows = [[], []];
}

$curDefault = old('currency_id', $inv['currency_id'] ?? $baseId);

// account lookup for the searchable combo
$acctById = [];
foreach ($accounts as $a) {
    $acctById[(int) $a['id']] = ['code' => $a['code'], 'name' => $a['name']];
}
$acctCell = static function ($selId) use ($acctById) {
    $sel   = $acctById[(int) $selId] ?? null;
    $label = $sel ? esc($sel['code'] . ' · ' . $sel['name'], 'attr') : '';

    return '<div class="combo" data-combo="account">'
        . '<input type="hidden" name="line_account[]" value="' . ($selId !== '' && $selId !== null ? (int) $selId : '') . '">'
        . '<input class="combo-input" autocomplete="off" spellcheck="false" placeholder="' . esc(lang('App.account'), 'attr') . '…" value="' . $label . '" title="' . $label . '">'
        . '<div class="combo-pop" hidden></div>'
        . '</div>';
};
$jobOpt = static function ($sel) use ($jobs) {
    $h = '<option value="">—</option>';
    foreach ($jobs as $j) {
        $h .= '<option value="' . $j['id'] . '"' . ((string) $sel === (string) $j['id'] ? ' selected' : '') . '>' . esc($j['code']) . '</option>';
    }

    return $h;
};
?>

<?php $isCn = ($docType ?? 'invoice') === 'credit_note'; ?>
<div class="page-head"><h1><?= esc($title) ?><?php if ($isCn): ?> <span class="badge badge-amber"><?= lang('Txn.credit_note') ?></span><?php endif ?></h1></div>

<?php $wasPosted = $isEdit && ($inv['status'] ?? '') === 'posted'; ?>
<?php if ($wasPosted): ?>
  <div class="alert alert-info"><?= lang('Txn.posted_note_full') ?></div>
<?php elseif ($isCn): ?>
  <div class="alert alert-info"><?= lang('Txn.cn_note_sales') ?></div>
<?php endif ?>

<form method="post" action="<?= $action ?>" id="piform">
  <?= csrf_field() ?>
  <input type="hidden" name="doc_type" value="<?= esc($docType ?? 'invoice') ?>">

  <div class="card">
    <div class="row">
      <div class="field">
        <label><?= lang('Txn.customer') ?></label>
        <?= view('partials/party_combo', [
            'field'       => 'customer_id',
            'items'       => $customers,
            'selected'    => old('customer_id', $inv['customer_id'] ?? ''),
            'placeholder' => lang('Txn.party_search_customer'),
            'required'    => true,
        ]) ?>
      </div>
      <div class="field" style="max-width:200px"><label><?= lang('Txn.customer_inv_no') ?></label><input name="customer_ref" value="<?= esc(old('customer_ref', $inv['customer_ref'] ?? '')) ?>"></div>
      <div class="field" style="max-width:160px"><label><?= lang('Txn.invoice_date') ?></label><input type="date" name="invoice_date" value="<?= esc(old('invoice_date', $inv['invoice_date'] ?? date('Y-m-d'))) ?>" required></div>
      <div class="field" style="max-width:160px"><label><?= lang('App.due_date') ?></label><input type="date" name="due_date" value="<?= esc(old('due_date', $inv['due_date'] ?? '')) ?>"></div>
    </div>
    <div class="row">
      <div class="field" style="max-width:140px">
        <label><?= lang('App.currency') ?></label>
        <select name="currency_id" id="curSel" data-base="<?= $baseId ?>">
          <?php foreach ($currencies as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (string) $curDefault === (string) $c['id'] ? 'selected' : '' ?>><?= esc($c['code']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:180px" id="rateWrap">
        <label><?= lang('Txn.rate_to', [base_code()]) ?></label>
        <input name="exchange_rate" id="rateInp" type="number" step="0.00000001" value="<?= esc(old('exchange_rate', $inv['exchange_rate'] ?? 1)) ?>">
      </div>
      <div class="field"><label><?= lang('App.reference') ?></label><input name="description" value="<?= esc(old('description', $inv['description'] ?? '')) ?>" placeholder="<?= esc(lang('Txn.ref_hint_booking'), 'attr') ?>"></div>
    </div>
  </div>

  <div class="card">
    <div class="page-head" style="margin-bottom:8px">
      <h2 style="margin:0"><?= lang('Txn.lines') ?></h2>
      <div class="colmenu-wrap no-print">
        <button type="button" class="btn sm ghost" id="colToggle"><?= lang('Txn.columns_toggle') ?></button>
        <div class="colmenu" id="colMenu" hidden>
          <label><input type="checkbox" data-col="job"> <?= lang('Txn.job') ?></label>
          <label><input type="checkbox" data-col="service"> <?= lang('Txn.service_date') ?></label>
          <label><input type="checkbox" data-col="party"> <?= lang('Txn.booking_name') ?></label>
          <label><input type="checkbox" data-col="pax"> <?= lang('Txn.pax') ?></label>
          <label><input type="checkbox" data-col="duration"> <?= lang('Txn.duration') ?></label>
          <label><input type="checkbox" data-col="remarks"> <?= lang('Txn.remarks') ?></label>
        </div>
      </div>
    </div>
    <div class="lines-scroll">
    <table class="grid tight piline-grid" id="lineTable">
      <thead>
        <tr>
          <th class="c-acct"><?= lang('App.account') ?></th>
          <th class="c-desc"><?= lang('App.description') ?></th>
          <th class="c-job col-job"><?= lang('Txn.job') ?></th>
          <th class="c-num right"><?= lang('App.amount') ?></th>
          <th class="c-date col-service"><?= lang('Txn.service_date') ?></th>
          <th class="c-party col-party"><?= lang('Txn.booking_name') ?></th>
          <th class="c-pax col-pax"><?= lang('Txn.pax') ?></th>
          <th class="c-dur col-duration"><?= lang('Txn.duration') ?></th>
          <th class="c-rmk col-remarks"><?= lang('Txn.remarks') ?></th>
          <th class="c-rm"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr class="lrow">
            <td>
              <?= $acctCell($r['account_id'] ?? '') ?>
              <input type="hidden" name="line_units[]" value="<?= esc($r['units'] ?? '') ?>">
              <input type="hidden" name="line_nights[]" value="<?= esc($r['nights'] ?? '') ?>">
              <input type="hidden" name="line_booking[]" value="<?= esc($r['booking_ref'] ?? '') ?>">
            </td>
            <td><input name="line_desc[]" value="<?= esc($r['description'] ?? '') ?>"></td>
            <td class="col-job"><select name="line_job[]"><?= $jobOpt($r['job_id'] ?? '') ?></select></td>
            <td><input name="line_amount[]" class="amt right mono" inputmode="decimal" value="<?= esc($r['amount'] ?? '') ?>"></td>
            <td class="col-service"><input type="date" name="line_service_date[]" value="<?= esc($r['service_date'] ?? '') ?>"></td>
            <td class="col-party"><input name="line_party[]" value="<?= esc($r['party_name'] ?? '') ?>"></td>
            <td class="col-pax"><input name="line_pax[]" class="right mono" value="<?= esc($r['pax'] ?? '') ?>"></td>
            <td class="col-duration"><input name="line_duration[]" class="right mono" value="<?= esc($r['duration'] ?? '') ?>"></td>
            <td class="col-remarks"><input name="line_remark[]" value="<?= esc($r['remark'] ?? '') ?>"></td>
            <td class="right"><button type="button" class="btn sm ghost rm" title="<?= esc(lang('Txn.remove_line'), 'attr') ?>">✕</button></td>
          </tr>
        <?php endforeach ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3" class="right muted small"><?= lang('Txn.subtotal') ?></td>
          <td class="right mono" id="subTot">0.00</td><td colspan="6"></td>
        </tr>
        <tr>
          <td colspan="3" class="right">
            PPN <input type="number" step="any" id="ppnRateInp" value="<?= esc($ppnRate) ?>" style="width:60px;display:inline-block;padding:2px 5px">%
            <button type="button" class="btn sm ghost" id="calcPpn"><?= lang('Txn.calc') ?></button>
          </td>
          <td><input name="ppn_amount" id="ppnInp" class="right mono" inputmode="decimal" value="<?= esc(old('ppn_amount', $inv['ppn_amount'] ?? 0)) ?>"></td>
          <td colspan="6"></td>
        </tr>
        <tr>
          <td colspan="3" class="right">
            PPh 23 <input type="number" step="any" id="pphRateInp" value="<?= esc($pphRate) ?>" style="width:60px;display:inline-block;padding:2px 5px">% (<?= lang('Txn.withheld') ?>)
            <button type="button" class="btn sm ghost" id="calcPph"><?= lang('Txn.calc') ?></button>
          </td>
          <td><input name="pph_amount" id="pphInp" class="right mono" inputmode="decimal" value="<?= esc(old('pph_amount', $inv['pph_amount'] ?? 0)) ?>"></td>
          <td colspan="6"></td>
        </tr>
        <tr class="subtotal">
          <td colspan="3" class="right"><?= lang('Txn.receivable_from_customer') ?></td>
          <td class="right mono" id="grandTot">0.00</td><td colspan="6"></td>
        </tr>
      </tfoot>
    </table>
    </div>
    <div style="margin-top:8px"><button type="button" class="btn sm secondary" id="addRow"><?= lang('App.add_line') ?></button></div>
  </div>

  <?php if ($cfDefs): ?>
    <div class="card"><?= view('partials/custom_fields', ['cfDefs' => $cfDefs, 'cfValues' => $cfValues]) ?></div>
  <?php endif ?>

  <div class="card">
    <div class="btn-group">
      <?php if ($wasPosted): ?>
        <button class="btn" type="submit" name="action" value="post"><?= lang('Txn.save_repost') ?></button>
      <?php else: ?>
        <button class="btn ghost" type="submit" name="action" value="draft"><?= lang('Txn.save_draft') ?></button>
        <?php if ($canPost): ?><button class="btn" type="submit" name="action" value="post"><?= $isCn ? lang('Txn.cn_save_post') : lang('Txn.save_post') ?></button><?php endif ?>
      <?php endif ?>
      <a class="btn ghost" href="<?= site_url('sales') ?>"><?= lang('App.cancel') ?></a>
      
    </div>
  </div>
</form>

<style>
  .piline-grid { min-width: 1160px; }
  .piline-grid th.c-acct  { width: 300px; }
  .piline-grid th.c-job   { width: 88px; }
  .piline-grid th.c-num   { width: 110px; }
  .piline-grid th.c-date  { width: 148px; }
  .piline-grid th.c-party { width: 190px; }
  .piline-grid th.c-pax   { width: 62px; }
  .piline-grid th.c-dur   { width: 76px; }
  .piline-grid th.c-rmk   { width: 180px; }
  .piline-grid th.c-rm    { width: 34px; }
</style>
<?= view('partials/line_grid_assets', ['accounts' => $accounts]) ?>

<template id="rowTpl">
  <tr class="lrow">
    <td>
      <?= $acctCell('') ?>
      <input type="hidden" name="line_units[]"><input type="hidden" name="line_nights[]"><input type="hidden" name="line_booking[]">
    </td>
    <td><input name="line_desc[]"></td>
    <td class="col-job"><select name="line_job[]"><?= $jobOpt('') ?></select></td>
    <td><input name="line_amount[]" class="amt right mono" inputmode="decimal"></td>
    <td class="col-service"><input type="date" name="line_service_date[]"></td>
    <td class="col-party"><input name="line_party[]"></td>
    <td class="col-pax"><input name="line_pax[]" class="right mono"></td>
    <td class="col-duration"><input name="line_duration[]" class="right mono"></td>
    <td class="col-remarks"><input name="line_remark[]"></td>
    <td class="right"><button type="button" class="btn sm ghost rm" title="<?= esc(lang('Txn.remove_line'), 'attr') ?>">✕</button></td>
  </tr>
</template>

<script>
(function () {
  var tbody = document.querySelector('#lineTable tbody'), tpl = document.getElementById('rowTpl');
  function num(v){ return parseFloat(String(v).replace(/[, ]/g,'')) || 0; }
  function recalc() {
    var sub = 0;
    tbody.querySelectorAll('tr.lrow .amt').forEach(function (i) { sub += num(i.value); });
    document.getElementById('subTot').textContent = sub.toLocaleString('en-US',{minimumFractionDigits:2});
    var g = sub + num(document.getElementById('ppnInp').value) - num(document.getElementById('pphInp').value);
    document.getElementById('grandTot').textContent = g.toLocaleString('en-US',{minimumFractionDigits:2});
    var sel = document.getElementById('curSel');
    document.getElementById('rateWrap').style.display = sel.value === sel.getAttribute('data-base') ? 'none' : '';
  }
  function subtotal(){ var s=0; tbody.querySelectorAll('tr.lrow .amt').forEach(function(i){ s+=num(i.value); }); return s; }
  function wire(row) {
    row.querySelector('.amt').addEventListener('input', recalc);
    var combo = row.querySelector('.combo');
    if (combo && window.initAccountCombo) { window.initAccountCombo(combo); }
    row.querySelector('.rm').addEventListener('click', function () {
      if (tbody.querySelectorAll('tr.lrow').length > 1) { row.remove(); recalc(); }
    });
  }
  tbody.querySelectorAll('tr.lrow').forEach(wire);
  if (window.setupColToggle) {
    window.setupColToggle({ table: '#lineTable', btn: '#colToggle', menu: '#colMenu', storeKey: 'si.cols' });
  }
  document.getElementById('addRow').addEventListener('click', function () {
    var frag = tpl.content.cloneNode(true), main = frag.querySelector('tr.lrow');
    tbody.appendChild(frag); wire(main);
    if (window.__applyCols) { window.__applyCols(); }
    var ci = main.querySelector('.combo-input'); if (ci) { ci.focus(); }
  });
  document.getElementById('calcPpn').addEventListener('click', function () {
    document.getElementById('ppnInp').value = (subtotal() * num(document.getElementById('ppnRateInp').value) / 100).toFixed(2); recalc();
  });
  document.getElementById('calcPph').addEventListener('click', function () {
    document.getElementById('pphInp').value = (subtotal() * num(document.getElementById('pphRateInp').value) / 100).toFixed(2); recalc();
  });
  ['ppnInp','pphInp'].forEach(function (id) { document.getElementById(id).addEventListener('input', recalc); });
  document.getElementById('curSel').addEventListener('change', recalc);
  recalc();
})();
</script>

<?= view('partials/job_drawer') ?>

<?= $this->endSection() ?>
