<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div>
    <h1><?= lang('Txn.apply_h', [esc($dep['payment_no'])]) ?></h1>
    <div class="muted small"><?= lang('Txn.unapplied_prefix', ['<span class="mono">' . money($dep['unapplied']) . '</span>']) ?> · <?= lang('Txn.rate_prefix', [money($dep['exchange_rate'], 4)]) ?></div>
  </div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('purchases/payments/' . $dep['id']) ?>"><?= lang('App.back') ?></a></div>
</div>

<?php if (! $open): ?>
  <div class="card"><p class="muted"><?= lang('Txn.no_open_inv_dep_ccy') ?></p></div>
<?php else: ?>
  <form method="post" action="<?= site_url('purchases/payments/' . $dep['id'] . '/apply') ?>" id="af">
    <?= csrf_field() ?>
    <div class="card">
      <div class="row">
        <div class="field" style="max-width:170px"><label><?= lang('Txn.apply_date') ?></label><input type="date" name="apply_date" value="<?= date('Y-m-d') ?>" required></div>
      </div>
      <table class="grid tight">
        <thead><tr><th><?= lang('Report.col_no') ?></th><th><?= lang('App.date') ?></th><th><?= lang('Report.col_ref') ?></th><th class="right"><?= lang('Txn.outstanding') ?></th><th class="right"><?= lang('Txn.apply_this') ?></th></tr></thead>
        <tbody>
          <?php foreach ($open as $inv): $out = round((float) $inv['outstanding'], 2); ?>
            <tr>
              <td class="mono nowrap"><?= esc($inv['internal_no']) ?></td>
              <td class="nowrap"><?= date_id($inv['invoice_date']) ?></td>
              <td class="small"><?= esc($inv['supplier_ref']) ?></td>
              <td class="right mono"><?= money($out) ?></td>
              <td><input class="right mono alloc" name="alloc[<?= $inv['id'] ?>]" inputmode="decimal" data-max="<?= $out ?>"></td>
            </tr>
          <?php endforeach ?>
        </tbody>
        <tfoot><tr class="subtotal"><td colspan="4" class="right"><?= lang('Txn.total_to_apply') ?></td><td class="right mono" id="tot">0.00</td></tr></tfoot>
      </table>
      <div class="btn-group" style="margin-top:10px">
        <button type="button" class="btn sm ghost" id="fill"><?= lang('Txn.use_full_deposit') ?></button>
        <button type="button" class="btn sm ghost" id="clr"><?= lang('Txn.clear') ?></button>
      </div>
    </div>
    <div class="card">
      <button class="btn" type="submit"><?= lang('Txn.apply_post') ?></button>
      <a class="btn ghost" href="<?= site_url('purchases/payments/' . $dep['id']) ?>"><?= lang('App.cancel') ?></a>
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
