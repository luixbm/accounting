<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$ccyOptions = []; // code => id
foreach ($open as $i) {
    $ccyOptions[$i['currency_code'] ?: $baseCode] = (int) $i['currency_id'];
}
ksort($ccyOptions);
?>

<div class="page-head"><h1>Receive from Customer</h1></div>

<form class="filterbar no-print" method="get" action="<?= site_url('sales/receipts/new') ?>">
  <div class="field" style="min-width:240px">
    <label>Customer</label>
    <select name="customer_id" onchange="this.form.submit()">
      <option value="">— choose a customer —</option>
      <?php foreach ($customers as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $customerId === (int) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option>
      <?php endforeach ?>
    </select>
  </div>
</form>

<?php if ($customerId && ! $open): ?>
  <div class="card"><p class="muted">No open invoices for this customer.</p></div>
<?php elseif ($customerId): ?>
  <form method="post" action="<?= site_url('sales/receipts') ?>" id="payform">
    <?= csrf_field() ?>
    <input type="hidden" name="customer_id" value="<?= $customerId ?>">

    <div class="card">
      <div class="row">
        <div class="field" style="max-width:160px"><label>Receipt date</label><input type="date" name="receipt_date" value="<?= date('Y-m-d') ?>" required></div>
        <div class="field" style="max-width:130px">
          <label>Currency</label>
          <select name="currency_id" id="ccySel" data-base="<?= esc($baseCode, 'attr') ?>">
            <?php foreach ($ccyOptions as $code => $cid): ?>
              <option value="<?= $cid ?>" data-code="<?= esc($code, 'attr') ?>" data-rate="<?= esc((string) ($rates[$code] ?? 1), 'attr') ?>"><?= esc($code) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="field" style="max-width:170px" id="rateWrap">
          <label>Rate <span class="muted small">(<?= esc($baseCode) ?> per 1)</span></label>
          <input name="exchange_rate" id="rateInp" class="mono right" inputmode="decimal" value="1">
        </div>
        <div class="field" style="max-width:240px">
          <label>Receive into</label>
          <select name="bank_account_id" required>
            <option value="">— bank / cash —</option>
            <?php foreach ($banks as $b): ?>
              <option value="<?= $b['id'] ?>"><?= esc($b['code'] . ' · ' . $b['name']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="field"><label>Reference</label><input name="reference"></div>
      </div>
      <p class="muted small">A receipt settles one currency at a time. A/R clears at each invoice's rate; the rate difference posts to realized FX.</p>
    </div>

    <div class="card">
      <h2>Open invoices</h2>
      <table class="grid tight">
        <thead><tr><th>No.</th><th>Date</th><th>Ref</th><th>Cur</th><th class="right">Outstanding</th><th class="right">Receive this</th></tr></thead>
        <tbody>
          <?php foreach ($open as $inv): ?>
            <?php $code = $inv['currency_code'] ?: $baseCode; $out = round((float) $inv['outstanding'], 2); ?>
            <tr data-ccy="<?= esc($code, 'attr') ?>">
              <td class="mono nowrap"><?= esc($inv['internal_no']) ?></td>
              <td class="nowrap"><?= date_id($inv['invoice_date']) ?></td>
              <td class="small"><?= esc($inv['customer_ref']) ?></td>
              <td class="mono small"><?= esc($code) ?></td>
              <td class="right mono"><?= money($out) ?></td>
              <td><input class="right mono alloc" name="alloc[<?= $inv['id'] ?>]" inputmode="decimal" data-max="<?= $out ?>" value="" title="Click to fill with the outstanding amount, then edit if needed"></td>
            </tr>
          <?php endforeach ?>
        </tbody>
        <tfoot>
          <tr class="subtotal"><td colspan="5" class="right">Total received <span id="totCcy" class="muted"></span></td><td class="right mono" id="payTot">0.00</td></tr>
        </tfoot>
      </table>
      <div class="btn-group" style="margin-top:10px">
        <button type="button" class="btn sm ghost" id="fillAll">Receive all in full</button>
        <button type="button" class="btn sm ghost" id="clearAll">Clear</button>
      </div>
    </div>

    <div class="card">
      <button class="btn" type="submit">Record &amp; post receipt</button>
      <a class="btn ghost" href="<?= site_url('sales/receipts') ?>">Cancel</a>
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
        var inp = r.querySelector('.alloc');
        if (r.getAttribute('data-ccy') === cur()) t += num(inp.value);
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
        var match = r.getAttribute('data-ccy') === c,
            inp   = r.querySelector('.alloc');
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
