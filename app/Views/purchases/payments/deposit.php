<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head"><h1><?= lang('Txn.record_supplier_deposit_h') ?></h1></div>

<form method="post" action="<?= site_url('purchases/payments/deposit') ?>">
  <?= csrf_field() ?>
  <div class="card" style="max-width:640px">
    <div class="row">
      <div class="field" style="min-width:220px">
        <label><?= lang('Txn.supplier') ?></label>
        <select name="supplier_id" required>
          <option value=""><?= lang('App.choose') ?></option>
          <?php foreach ($suppliers as $s): ?>
            <option value="<?= $s['id'] ?>" <?= (int) old('supplier_id', $supplierId) === (int) $s['id'] ? 'selected' : '' ?>><?= esc($s['name']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:160px"><label><?= lang('App.date') ?></label><input type="date" name="payment_date" value="<?= esc(old('payment_date', date('Y-m-d'))) ?>" required></div>
    </div>
    <div class="row">
      <div class="field" style="max-width:130px">
        <label><?= lang('App.currency') ?></label>
        <select name="currency_id" id="ccySel" data-base="<?= esc($baseCode, 'attr') ?>">
          <?php foreach ($currencies as $c): ?>
            <option value="<?= $c['id'] ?>" data-code="<?= esc($c['code'], 'attr') ?>" data-rate="<?= esc((string) ($rates[$c['code']] ?? 1), 'attr') ?>" <?= old('currency_id') == $c['id'] ? 'selected' : '' ?>><?= esc($c['code']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:170px" id="rateWrap">
        <label><?= lang('Txn.rate_per_1') ?> <span class="muted small"><?= lang('Txn.per_1_note', [esc($baseCode)]) ?></span></label>
        <input name="exchange_rate" id="rateInp" class="mono right" inputmode="decimal" value="<?= esc(old('exchange_rate', '1')) ?>">
      </div>
      <div class="field" style="max-width:200px"><label><?= lang('App.amount') ?></label><input name="amount" class="mono right" inputmode="decimal" value="<?= esc(old('amount')) ?>" required></div>
    </div>
    <div class="row">
      <div class="field" style="max-width:280px">
        <label><?= lang('Txn.pay_from') ?></label>
        <select name="bank_account_id" required>
          <option value=""><?= lang('Txn.bank_cash_choose') ?></option>
          <?php foreach ($banks as $b): ?><option value="<?= $b['id'] ?>" <?= old('bank_account_id') == $b['id'] ? 'selected' : '' ?>><?= esc($b['code'] . ' · ' . $b['name']) ?></option><?php endforeach ?>
        </select>
      </div>
      <div class="field"><label><?= lang('App.reference') ?></label><input name="reference" value="<?= esc(old('reference')) ?>"></div>
    </div>
    <p class="muted small"><?= lang('Txn.deposit_books_note') ?></p>
  </div>
  <div class="card">
    <button class="btn" type="submit"><?= lang('Txn.record_post_payment') ?></button>
    <a class="btn ghost" href="<?= site_url('purchases/payments') ?>"><?= lang('App.cancel') ?></a>
  </div>
</form>

<script>
(function () {
  var sel = document.getElementById('ccySel'), base = sel.getAttribute('data-base'),
      rw = document.getElementById('rateWrap'), ri = document.getElementById('rateInp');
  function sync() {
    var o = sel.options[sel.selectedIndex], isBase = o && o.getAttribute('data-code') === base;
    rw.style.display = isBase ? 'none' : '';
    if (isBase) ri.value = '1'; else if (o && (ri.value === '' || ri.value === '1')) ri.value = o.getAttribute('data-rate');
  }
  sel.addEventListener('change', sync); sync();
})();
</script>

<?= $this->endSection() ?>
