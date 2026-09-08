<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$ccyOptions = []; // code => id
foreach ($open as $i) {
    $ccyOptions[$i['currency_code'] ?: $baseCode] = (int) $i['currency_id'];
}
ksort($ccyOptions);
?>

<div class="page-head"><h1><?= lang('Txn.pay_supplier_h') ?></h1></div>

<form class="filterbar no-print" method="get" action="<?= site_url('purchases/payments/new') ?>">
  <div class="field" style="min-width:240px">
    <label><?= lang('Txn.supplier') ?></label>
    <select name="supplier_id" onchange="this.form.submit()">
      <option value=""><?= lang('Txn.choose_supplier') ?></option>
      <?php foreach ($suppliers as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $supplierId === (int) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option>
      <?php endforeach ?>
    </select>
  </div>
</form>

<?php if ($supplierId): ?>
  <?= view('partials/deposit_notice', [
      'deposits'    => $deposits ?? [],
      'applyBase'   => 'purchases/payments',
      'noun'        => 'supplier',
      'depositWord' => 'deposit',
  ]) ?>
<?php endif ?>

<?php if ($supplierId && ! $open): ?>
  <div class="card"><p class="muted"><?= lang('Txn.no_open_inv_supplier') ?></p></div>
<?php elseif ($supplierId): ?>
  <form method="post" action="<?= site_url('purchases/payments') ?>" id="payform">
    <?= csrf_field() ?>
    <input type="hidden" name="supplier_id" value="<?= $supplierId ?>">

    <div class="card">
      <div class="row">
        <div class="field" style="max-width:160px"><label><?= lang('Txn.payment_date') ?></label><input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required></div>
        <div class="field" style="max-width:130px">
          <label><?= lang('App.currency') ?></label>
          <select name="currency_id" id="ccySel" data-base="<?= esc($baseCode, 'attr') ?>">
            <?php foreach ($ccyOptions as $code => $cid): ?>
              <option value="<?= $cid ?>" data-code="<?= esc($code, 'attr') ?>" data-rate="<?= esc((string) ($rates[$code] ?? 1), 'attr') ?>"><?= esc($code) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="field" style="max-width:170px" id="rateWrap">
          <label><?= lang('Txn.rate_per_1') ?> <span class="muted small"><?= lang('Txn.per_1_note', [esc($baseCode)]) ?></span></label>
          <input name="exchange_rate" id="rateInp" class="mono right" inputmode="decimal" value="1">
        </div>
        <div class="field" style="max-width:240px">
          <label><?= lang('Txn.pay_from') ?></label>
          <select name="bank_account_id" required>
            <option value=""><?= lang('Txn.bank_cash_choose') ?></option>
            <?php foreach ($banks as $b): ?>
              <option value="<?= $b['id'] ?>"><?= esc($b['code'] . ' · ' . $b['name']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="field"><label><?= lang('App.reference') ?></label><input name="reference"></div>
      </div>
      <p class="muted small"><?= lang('Txn.settle_note_ap') ?></p>
    </div>

    <div class="card">
      <h2><?= lang('Txn.open_invoices_h') ?></h2>
      <table class="grid tight">
        <thead><tr><th><?= lang('Report.col_no') ?></th><th><?= lang('App.date') ?></th><th><?= lang('Report.col_ref') ?></th><th><?= lang('Txn.cur') ?></th><th class="right"><?= lang('Txn.outstanding') ?></th><th class="right"><?= lang('Txn.pay_this') ?></th></tr></thead>
        <tbody>
          <?php foreach ($open as $inv): ?>
            <?php
              $code = $inv['currency_code'] ?: $baseCode;
              $out  = round((float) $inv['outstanding'], 2);
              $isCn = ($inv['doc_type'] ?? 'invoice') === 'credit_note';
            ?>
            <tr data-ccy="<?= esc($code, 'attr') ?>" data-cn="<?= $isCn ? '1' : '0' ?>">
              <td class="mono nowrap"><?= esc($inv['internal_no']) ?><?php if ($isCn): ?> <span class="badge badge-amber" title="<?= esc(lang('Txn.doc_credit_note'), 'attr') ?>">CN</span><?php endif ?></td>
              <td class="nowrap"><?= date_id($inv['invoice_date']) ?></td>
              <td class="small"><?= esc($inv['supplier_ref']) ?></td>
              <td class="mono small"><?= esc($code) ?></td>
              <td class="right mono"><?= $isCn ? '(' . money($out) . ')' : money($out) ?></td>
              <td><input class="right mono alloc" name="alloc[<?= $inv['id'] ?>]" inputmode="decimal" data-max="<?= $out ?>" data-sign="<?= $isCn ? '-1' : '1' ?>" value="" title="<?= esc(lang('Txn.pay_this_hint'), 'attr') ?>"></td>
            </tr>
          <?php endforeach ?>
        </tbody>
        <tfoot>
          <tr class="subtotal"><td colspan="5" class="right"><?= lang('Txn.total_payment') ?> <span id="totCcy" class="muted"></span></td><td class="right mono" id="payTot">0.00</td></tr>
        </tfoot>
      </table>
      <div class="btn-group" style="margin-top:10px">
        <button type="button" class="btn sm ghost" id="fillAll"><?= lang('Txn.pay_all_full') ?></button>
        <button type="button" class="btn sm ghost" id="clearAll"><?= lang('Txn.clear') ?></button>
      </div>
    </div>

    <div class="card">
      <button class="btn" type="submit"><?= lang('Txn.record_post_payment') ?></button>
      <a class="btn ghost" href="<?= site_url('purchases/payments') ?>"><?= lang('App.cancel') ?></a>
    </div>
  </form>

  <script>
  (function () {
    var sel   = document.getElementById('ccySel'),
        base  = sel.getAttribute('data-base'),
        rateW = document.getElementById('rateWrap'),
        rateI = document.getElementById('rateInp'),
        totC  = document.getElementById('totCcy'),
        rows  = [].slice.call(document.querySelectorAll('#payform tbody tr'));
    function num(v){ return parseFloat(String(v).replace(/[, ]/g,'')) || 0; }
    function cur(){ var o = sel.options[sel.selectedIndex]; return o ? o.getAttribute('data-code') : base; }
    function tot() {
      var t = 0;
      rows.forEach(function (r) {
        if (r.getAttribute('data-ccy') !== cur()) return;
        var inp = r.querySelector('.alloc');
        t += num(inp.value) * (parseFloat(inp.getAttribute('data-sign')) || 1);
      });
      document.getElementById('payTot').textContent = t.toLocaleString('en-US',{minimumFractionDigits:2});
    }
    function sync() {
      var c = cur(), isBase = c === base;
      rateW.style.display = isBase ? 'none' : '';
      var opt = sel.options[sel.selectedIndex];
      rateI.value = isBase ? '1' : (opt ? opt.getAttribute('data-rate') : '1');
      totC.textContent = c;
      rows.forEach(function (r) {
        var match = r.getAttribute('data-ccy') === c, inp = r.querySelector('.alloc');
        r.style.opacity = match ? '' : '.4';
        inp.disabled = !match;
        if (!match) inp.value = '';
      });
      tot();
    }
    sel.addEventListener('change', sync);
    rows.forEach(function (r) {
      var inp = r.querySelector('.alloc');
      inp.addEventListener('input', tot);
      // click an empty cell -> prefill the outstanding amount (still editable)
      inp.addEventListener('click', function () {
        if (!inp.disabled && num(inp.value) === 0) {
          inp.value = parseFloat(inp.getAttribute('data-max')).toFixed(2);
          inp.select();
          tot();
        }
      });
    });
    document.getElementById('fillAll').addEventListener('click', function () {
      rows.forEach(function (r) {
        var inp = r.querySelector('.alloc');
        if (r.getAttribute('data-ccy') === cur()) inp.value = parseFloat(inp.getAttribute('data-max')).toFixed(2);
      });
      tot();
    });
    document.getElementById('clearAll').addEventListener('click', function () {
      rows.forEach(function (r) { r.querySelector('.alloc').value = ''; }); tot();
    });
    sync();
  })();
  </script>
<?php endif ?>

<?= $this->endSection() ?>
