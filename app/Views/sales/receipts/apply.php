<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $cc = $dep['currency_code'] ?? ''; ?>

<div class="page-head">
  <div>
    <h1>Apply <?= esc($dep['receipt_no']) ?></h1>
    <div class="muted small">Unapplied: <span class="mono"><?= money($dep['unapplied']) ?></span> · rate <?= money($dep['exchange_rate'], 4) ?></div>
  </div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('sales/receipts/' . $dep['id']) ?>">Back</a></div>
</div>

<?php if (! $open): ?>
  <div class="card"><p class="muted">No open invoices for this customer in the deposit's currency.</p></div>
<?php else: ?>
  <form method="post" action="<?= site_url('sales/receipts/' . $dep['id'] . '/apply') ?>" id="af">
    <?= csrf_field() ?>
    <div class="card">
      <div class="row">
        <div class="field" style="max-width:170px"><label>Apply date</label><input type="date" name="apply_date" value="<?= date('Y-m-d') ?>" required></div>
      </div>
      <table class="grid tight">
        <thead><tr><th>No.</th><th>Date</th><th>Ref</th><th class="right">Outstanding</th><th class="right">Apply this</th></tr></thead>
        <tbody>
          <?php foreach ($open as $inv): $out = round((float) $inv['outstanding'], 2); ?>
            <tr>
              <td class="mono nowrap"><?= esc($inv['internal_no']) ?></td>
              <td class="nowrap"><?= date_id($inv['invoice_date']) ?></td>
              <td class="small"><?= esc($inv['customer_ref']) ?></td>
              <td class="right mono"><?= money($out) ?></td>
              <td><input class="right mono alloc" name="alloc[<?= $inv['id'] ?>]" inputmode="decimal" data-max="<?= $out ?>"></td>
            </tr>
          <?php endforeach ?>
        </tbody>
        <tfoot><tr class="subtotal"><td colspan="4" class="right">Total to apply</td><td class="right mono" id="tot">0.00</td></tr></tfoot>
      </table>
      <div class="btn-group" style="margin-top:10px">
        <button type="button" class="btn sm ghost" id="fill">Use full deposit</button>
        <button type="button" class="btn sm ghost" id="clr">Clear</button>
      </div>
    </div>
    <div class="card">
      <button class="btn" type="submit">Apply &amp; post</button>
      <a class="btn ghost" href="<?= site_url('sales/receipts/' . $dep['id']) ?>">Cancel</a>
    </div>
  </form>

  <script>
  (function () {
    var rows = [].slice.call(document.querySelectorAll('.alloc')),
        cap  = <?= json_encode((float) $dep['unapplied']) ?>;
    function num(v){ return parseFloat(String(v).replace(/[, ]/g,'')) || 0; }
    function tot(){ var t = 0; rows.forEach(function(r){ t += num(r.value); });
      document.getElementById('tot').textContent = t.toLocaleString('en-US',{minimumFractionDigits:2}); }
    rows.forEach(function(r){ r.addEventListener('input', tot); });
    document.getElementById('fill').addEventListener('click', function () {
      var left = cap;
      rows.forEach(function (r) {
        var m = Math.min(parseFloat(r.getAttribute('data-max')), left);
        r.value = m > 0 ? m.toFixed(2) : '';
        left = Math.max(0, left - Math.max(0, m));
      });
      tot();
    });
    document.getElementById('clr').addEventListener('click', function () { rows.forEach(function(r){ r.value=''; }); tot(); });
    tot();
  })();
  </script>
<?php endif ?>

<?= $this->endSection() ?>
