<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1>Import bank statement</h1><div class="muted small">Upload the bank's .xlsx or .csv export, then map its columns.</div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('banking/reconcile') ?>">Cancel</a></div>
</div>

<form method="post" action="<?= site_url('banking/reconcile') ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="card">
    <div class="row">
      <div class="field" style="max-width:320px">
        <label>Bank / cash account</label>
        <select name="bank_account_id" required>
          <option value="">— account —</option>
          <?php foreach ($banks as $b): ?>
            <option value="<?= $b['id'] ?>" <?= old('bank_account_id') == $b['id'] ? 'selected' : '' ?>><?= esc($b['code'] . ' · ' . $b['name']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:180px">
        <label>Statement date</label>
        <input type="date" name="statement_date" value="<?= esc(old('statement_date', date('Y-m-d'))) ?>" required>
      </div>
    </div>
    <div class="row">
      <div class="field" style="max-width:220px">
        <label>Opening balance (per bank)</label>
        <input name="opening_balance" class="right mono" inputmode="decimal" value="<?= esc(old('opening_balance', '0')) ?>">
      </div>
      <div class="field" style="max-width:220px">
        <label>Closing balance (per bank)</label>
        <input name="closing_balance" class="right mono" inputmode="decimal" value="<?= esc(old('closing_balance', '0')) ?>">
      </div>
      <div class="field"><label>Note (optional)</label><input name="note" value="<?= esc(old('note')) ?>"></div>
    </div>
    <div class="field">
      <label>Statement file (.xlsx, .xls, .csv)</label>
      <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
    </div>
  </div>
  <div class="card">
    <div class="btn-group">
      <button class="btn" type="submit">Upload &amp; continue</button>
      <a class="btn ghost" href="<?= site_url('banking/reconcile') ?>">Cancel</a>
    </div>
  </div>
</form>

<?= $this->endSection() ?>
