<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$isSpend = $mode === 'spend';
$accOpt = static function ($accounts) {
    $h = '<option value="">— account —</option>';
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

<div class="page-head"><h1><?= $isSpend ? 'Spend Money' : 'Receive Money' ?></h1></div>

<form method="post" action="<?= site_url('banking/' . $mode) ?>" id="mform">
  <?= csrf_field() ?>
  <div class="card">
    <div class="row">
      <div class="field" style="max-width:260px">
        <label><?= $isSpend ? 'Pay from' : 'Receive into' ?></label>
        <select name="bank_account" required>
          <option value="">— bank / cash —</option>
          <?php foreach ($banks as $b): ?><option value="<?= $b['id'] ?>" <?= old('bank_account') == $b['id'] ? 'selected' : '' ?>><?= esc($b['code'] . ' · ' . $b['name']) ?></option><?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:170px"><label>Date</label><input type="date" name="date" value="<?= esc(old('date', date('Y-m-d'))) ?>" required></div>
      <div class="field"><label>Reference</label><input name="reference" value="<?= esc(old('reference')) ?>"></div>
    </div>
    <div class="field"><label>Memo</label><input name="memo" value="<?= esc(old('memo')) ?>"></div>
  </div>

  <div class="card">
    <h2><?= $isSpend ? 'What was it for?' : 'What is it for?' ?></h2>
    <table class="grid tight" id="lineTable">
      <thead><tr><th style="width:34%">Account</th><th>Description</th><th style="width:9%">Job</th><th style="width:16%" class="right">Amount</th><th></th></tr></thead>
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
        <tr><td colspan="3" class="right"><button type="button" class="btn sm secondary" id="addRow">+ Add line</button></td>
          <td class="right mono" id="tot">0.00</td><td></td></tr>
      </tfoot>
    </table>
  </div>

  <div class="card">
    <div class="btn-group">
      <button class="btn" type="submit">Post</button>
      <a class="btn ghost" href="<?= site_url('banking') ?>">Cancel</a>
      <span class="muted small" style="align-self:center">Books: <?= $isSpend ? 'Dr the accounts above / Cr bank' : 'Dr bank / Cr the accounts above' ?>.</span>
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
