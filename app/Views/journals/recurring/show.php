<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php

// Working line rows: old() input wins, then saved lines, else 2 blanks.
$oldAcc = old('line_account');
if (is_array($oldAcc)) {
    $rows = [];
    foreach ($oldAcc as $i => $a) {
        $rows[] = [
            'account_id'  => $a,
            'memo'        => old('line_memo')[$i] ?? '',
            'debit'       => old('line_debit')[$i] ?? '',
            'credit'      => old('line_credit')[$i] ?? '',
            'customer_id' => old('line_customer')[$i] ?? '',
            'supplier_id' => old('line_supplier')[$i] ?? '',
            'job_id'      => old('line_job')[$i] ?? '',
        ];
    }
} elseif ($lines) {
    $rows = array_map(static fn ($l) => [
        'account_id'  => $l['account_id'],
        'memo'        => $l['memo'],
        'debit'       => (float) $l['debit'] > 0 ? $l['debit'] : '',
        'credit'      => (float) $l['credit'] > 0 ? $l['credit'] : '',
        'customer_id' => $l['customer_id'],
        'supplier_id' => $l['supplier_id'],
        'job_id'      => $l['job_id'] ?? '',
    ], $lines);
} else {
    $rows = [[], []];
}

$accOptions = static function ($selected) use ($accounts) {
    $out = '<option value="">' . lang('Txn.choose_account') . '</option>';
    foreach ($accounts as $a) {
        $sel = (string) $selected === (string) $a['id'] ? ' selected' : '';
        $out .= '<option value="' . $a['id'] . '" data-sub="' . esc($a['subledger'], 'attr') . '"' . $sel . '>'
            . esc($a['code'] . ' · ' . $a['name']) . '</option>';
    }

    return $out;
};
$partyOptions = static function ($list, $selected) {
    $out = '<option value="">—</option>';
    foreach ($list as $p) {
        $sel = (string) $selected === (string) $p['id'] ? ' selected' : '';
        $out .= '<option value="' . $p['id'] . '"' . $sel . '>' . esc($p['name']) . '</option>';
    }

    return $out;
};
$jobOptions = static function ($selected) use ($jobs) {
    $out = '<option value="">—</option>';
    foreach ($jobs as $j) {
        $sel = (string) $selected === (string) $j['id'] ? ' selected' : '';
        $out .= '<option value="' . $j['id'] . '"' . $sel . '>' . esc($j['code']) . '</option>';
    }

    return $out;
};
?>

<div class="page-head">
  <div>
    <h1><?= esc($tpl['name']) ?> <?= $tpl['is_active'] ? '' : '<span class="badge badge-gray">' . lang('Recurring.inactive') . '</span>' ?></h1>
    <div class="muted small">
      <?= esc($tpl['description']) ?> ·
      <?= esc(\App\Models\JournalModel::SOURCES[$tpl['source']] ?? $tpl['source']) ?> ·
      <?= esc($currencyOf) ?> ·
      <?= lang('Recurring.freq_' . $tpl['frequency']) ?>
      <?php if ($tpl['next_date']): ?> · <?= lang('Recurring.next_is', [date_id($tpl['next_date'])]) ?><?php endif ?>
      <?php if ($tpl['last_generated_on']): ?> · <?= lang('Recurring.last_was', [date_id($tpl['last_generated_on'])]) ?><?php endif ?>
    </div>
  </div>
  <div class="btn-group no-print">
    <a class="btn ghost" href="<?= site_url('journals/recurring/' . $tpl['id'] . '/edit') ?>"><?= lang('App.edit') ?></a>
    <a class="btn ghost" href="<?= site_url('journals/recurring') ?>"><?= lang('App.back') ?></a>
  </div>
</div>

<div class="card">
  <h2><?= lang('Recurring.generate_h') ?></h2>
  <form method="post" action="<?= site_url('journals/recurring/' . $tpl['id'] . '/generate') ?>" class="inline" style="gap:8px;flex-wrap:wrap">
    <?= csrf_field() ?>
    <label class="inline" style="font-weight:400;gap:6px">
      <span class="muted small"><?= lang('App.date') ?></span>
      <input type="date" name="entry_date" value="<?= esc($tpl['next_date'] ?: date('Y-m-d')) ?>" style="width:auto">
    </label>
    <button class="btn" type="submit"<?= $lines ? '' : ' disabled' ?>><?= lang('Recurring.generate_now') ?></button>
    <span class="muted small" style="align-self:center"><?= lang('Recurring.generate_hint') ?></span>
  </form>
</div>

<form method="post" action="<?= site_url('journals/recurring/' . $tpl['id'] . '/lines') ?>" id="jform">
  <?= csrf_field() ?>
  <div class="card">
    <h2><?= lang('Txn.lines') ?> <span class="muted small"><?= esc($currencyOf) ?></span></h2>
    <table class="grid tight" id="lineTable">
      <thead>
        <tr>
          <th style="width:24%"><?= lang('App.account') ?></th>
          <th><?= lang('App.memo') ?></th>
          <th style="width:15%"><?= lang('Txn.cust_supp') ?></th>
          <th style="width:9%"><?= lang('Txn.job') ?></th>
          <th style="width:13%" class="right"><?= lang('Txn.debit') ?></th>
          <th style="width:13%" class="right"><?= lang('Txn.credit') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr class="lrow">
            <td><select name="line_account[]" class="acc"><?= $accOptions($r['account_id'] ?? '') ?></select></td>
            <td><input name="line_memo[]" value="<?= esc($r['memo'] ?? '') ?>"></td>
            <td>
              <select name="line_customer[]" class="cust" style="display:none"><?= $partyOptions($customers, $r['customer_id'] ?? '') ?></select>
              <select name="line_supplier[]" class="supp" style="display:none"><?= $partyOptions($suppliers, $r['supplier_id'] ?? '') ?></select>
              <span class="party-na muted small">—</span>
            </td>
            <td><select name="line_job[]" class="job"><?= $jobOptions($r['job_id'] ?? '') ?></select></td>
            <td><input name="line_debit[]" class="amt dr right mono" inputmode="decimal" value="<?= esc($r['debit'] ?? '') ?>"></td>
            <td><input name="line_credit[]" class="amt cr right mono" inputmode="decimal" value="<?= esc($r['credit'] ?? '') ?>"></td>
            <td class="right"><button type="button" class="btn sm ghost rm">✕</button></td>
          </tr>
        <?php endforeach ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4" class="right"><button type="button" class="btn sm secondary" id="addRow"><?= lang('App.add_line') ?></button></td>
          <td class="right mono" id="sumDr">0.00</td>
          <td class="right mono" id="sumCr">0.00</td>
          <td></td>
        </tr>
        <tr id="diffRow">
          <td colspan="4" class="right muted"><?= lang('Txn.difference') ?></td>
          <td colspan="2" class="right mono" id="diff">0.00</td>
          <td></td>
        </tr>
      </tfoot>
    </table>
    <div class="btn-group" style="margin-top:10px">
      <button class="btn" type="submit"><?= lang('Recurring.save_lines') ?></button>
      <span class="muted small" style="align-self:center"><?= lang('Recurring.lines_hint') ?></span>
    </div>
  </div>
</form>

<template id="rowTpl">
  <tr class="lrow">
    <td><select name="line_account[]" class="acc"><?= $accOptions('') ?></select></td>
    <td><input name="line_memo[]"></td>
    <td>
      <select name="line_customer[]" class="cust" style="display:none"><?= $partyOptions($customers, '') ?></select>
      <select name="line_supplier[]" class="supp" style="display:none"><?= $partyOptions($suppliers, '') ?></select>
      <span class="party-na muted small">—</span>
    </td>
    <td><select name="line_job[]" class="job"><?= $jobOptions('') ?></select></td>
    <td><input name="line_debit[]" class="amt dr right mono" inputmode="decimal"></td>
    <td><input name="line_credit[]" class="amt cr right mono" inputmode="decimal"></td>
    <td class="right"><button type="button" class="btn sm ghost rm">✕</button></td>
  </tr>
</template>

<script>
(function () {
  var tbody = document.querySelector('#lineTable tbody');
  var tpl = document.querySelector('#rowTpl');
  function num(v){ return parseFloat(String(v).replace(/[, ]/g,'')) || 0; }

  function syncParty(row){
    var opt = row.querySelector('.acc').selectedOptions[0];
    var sub = opt ? opt.getAttribute('data-sub') : 'none';
    var cust = row.querySelector('.cust'), supp = row.querySelector('.supp'), na = row.querySelector('.party-na');
    cust.style.display = sub === 'customer' ? '' : 'none';
    supp.style.display = sub === 'supplier' ? '' : 'none';
    na.style.display   = (sub === 'customer' || sub === 'supplier') ? 'none' : '';
    if (sub !== 'customer') cust.value = '';
    if (sub !== 'supplier') supp.value = '';
  }

  function recalc(){
    var dr = 0, cr = 0;
    tbody.querySelectorAll('tr.lrow').forEach(function(r){
      dr += num(r.querySelector('.dr').value);
      cr += num(r.querySelector('.cr').value);
    });
    document.getElementById('sumDr').textContent = dr.toLocaleString('en-US',{minimumFractionDigits:2});
    document.getElementById('sumCr').textContent = cr.toLocaleString('en-US',{minimumFractionDigits:2});
    var d = dr - cr;
    var diffEl = document.getElementById('diff');
    diffEl.textContent = d.toLocaleString('en-US',{minimumFractionDigits:2});
    diffEl.style.color = Math.abs(d) < 0.005 ? 'var(--green)' : 'var(--red)';
  }

  function wire(row){
    row.querySelector('.acc').addEventListener('change', function(){ syncParty(row); });
    row.querySelectorAll('.amt').forEach(function(i){ i.addEventListener('input', recalc); });
    row.querySelector('.rm').addEventListener('click', function(){
      if (tbody.querySelectorAll('tr.lrow').length > 1) { row.remove(); recalc(); }
    });
    syncParty(row);
  }

  tbody.querySelectorAll('tr.lrow').forEach(wire);
  document.getElementById('addRow').addEventListener('click', function(){
    var node = tpl.content.firstElementChild.cloneNode(true);
    tbody.appendChild(node);
    wire(node);
  });
  recalc();
})();
</script>

<?= $this->endSection() ?>
