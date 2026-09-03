<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$isSpend = $mode === 'spend';
$accOpt = static function ($accounts) {
    $h = '<option value="">' . esc(lang('Txn.choose_account')) . '</option>';
    foreach ($accounts as $a) {
        $h .= '<option value="' . $a['id'] . '">' . esc($a['code'] . ' · ' . $a['name']) . '</option>';
    }

    return $h;
};
$jobOpt = static function ($jobs) {
    $h = '<option value="">—</option>';
    foreach ($jobs as $j) {
        $h .= '<option value="' . $j['id'] . '">' . esc($j['code']) . '</option>';
    }

    return $h;
};
?>

<div class="page-head"><h1><?= $isSpend ? lang('Txn.spend_money_h') : lang('Txn.receive_money_h') ?></h1></div>

<form method="post" action="<?= site_url('banking/' . $mode) ?>" id="mform">
  <?= csrf_field() ?>
  <div class="card">
    <div class="row">
      <div class="field" style="max-width:260px">
        <label><?= $isSpend ? lang('Txn.pay_from') : lang('Txn.receive_into') ?></label>
        <select name="bank_account" required>
          <option value=""><?= lang('Txn.bank_cash_choose') ?></option>
          <?php foreach ($banks as $b): ?><option value="<?= $b['id'] ?>" <?= old('bank_account') == $b['id'] ? 'selected' : '' ?>><?= esc($b['code'] . ' · ' . $b['name']) ?></option><?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:170px"><label><?= lang('App.date') ?></label><input type="date" name="date" value="<?= esc(old('date', date('Y-m-d'))) ?>" required></div>
      <div class="field"><label><?= lang('App.reference') ?></label><input name="reference" value="<?= esc(old('reference')) ?>"></div>
    </div>
    <div class="field"><label><?= lang('App.memo') ?></label><input name="memo" value="<?= esc(old('memo')) ?>"></div>
  </div>

  <div class="card">
    <h2><?= $isSpend ? lang('Txn.what_for_spend') : lang('Txn.what_for_receive') ?></h2>
    <table class="grid tight" id="lineTable">
      <thead><tr><th style="width:34%"><?= lang('App.account') ?></th><th><?= lang('App.description') ?></th><th style="width:9%"><?= lang('Txn.job') ?></th><th style="width:16%" class="right"><?= lang('App.amount') ?></th><th></th></tr></thead>
      <tbody>
        <?php for ($i = 0; $i < 2; $i++): ?>
          <tr class="lrow">
            <td><select name="line_account[]"><?= $accOpt($accounts) ?></select></td>
            <td><input name="line_desc[]"></td>
            <td><select name="line_job[]"><?= $jobOpt($jobs) ?></select></td>
            <td><input name="line_amount[]" class="amt right mono" inputmode="decimal"></td>
            <td class="right"><button type="button" class="btn sm ghost rm">✕</button></td>
          </tr>
        <?php endfor ?>
      </tbody>
      <tfoot>
        <tr><td colspan="3" class="right"><button type="button" class="btn sm secondary" id="addRow"><?= lang('App.add_line') ?></button></td>
          <td class="right mono" id="tot">0.00</td><td></td></tr>
      </tfoot>
    </table>
  </div>

  <div class="card">
    <div class="btn-group">
      <button class="btn" type="submit"><?= lang('App.post') ?></button>
      <a class="btn ghost" href="<?= site_url('banking') ?>"><?= lang('App.cancel') ?></a>
      <span class="muted small" style="align-self:center"><?= $isSpend ? lang('Txn.books_spend') : lang('Txn.books_receive') ?></span>
    </div>
  </div>
</form>

<template id="rowTpl">
  <tr class="lrow">
    <td><select name="line_account[]"><?= $accOpt($accounts) ?></select></td>
    <td><input name="line_desc[]"></td>
    <td><select name="line_job[]"><?= $jobOpt($jobs) ?></select></td>
    <td><input name="line_amount[]" class="amt right mono" inputmode="decimal"></td>
    <td class="right"><button type="button" class="btn sm ghost rm">✕</button></td>
  </tr>
</template>

<script>
(function () {
  var tbody = document.querySelector('#lineTable tbody'), tpl = document.getElementById('rowTpl');
  function num(v){ return parseFloat(String(v).replace(/[, ]/g,'')) || 0; }
  function tot(){ var t=0; tbody.querySelectorAll('.amt').forEach(function(i){ t+=num(i.value); });
    document.getElementById('tot').textContent = t.toLocaleString('en-US',{minimumFractionDigits:2}); }
  function wire(r){ r.querySelector('.amt').addEventListener('input', tot);
    r.querySelector('.rm').addEventListener('click', function(){ if(tbody.querySelectorAll('tr.lrow').length>1){ r.remove(); tot(); } }); }
  tbody.querySelectorAll('tr.lrow').forEach(wire);
  document.getElementById('addRow').addEventListener('click', function(){ var n=tpl.content.firstElementChild.cloneNode(true); tbody.appendChild(n); wire(n); });
  tot();
})();
</script>

<?= $this->endSection() ?>
