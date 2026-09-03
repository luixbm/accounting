<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1><?= lang('Import.bk_new_h') ?></h1><div class="muted small"><?= lang('Import.bk_new_note') ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('banking/reconcile') ?>"><?= lang('App.cancel') ?></a></div>
</div>

<form method="post" action="<?= site_url('banking/reconcile') ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="card">
    <div class="row">
      <div class="field" style="max-width:320px">
        <label><?= lang('Import.bk_bank_cash_acct') ?></label>
        <select name="bank_account_id" required>
          <option value=""><?= lang('Import.bk_acct_opt') ?></option>
          <?php foreach ($banks as $b): ?>
            <option value="<?= $b['id'] ?>" <?= old('bank_account_id') == $b['id'] ? 'selected' : '' ?>><?= esc($b['code'] . ' · ' . $b['name']) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:180px">
        <label><?= lang('Import.bk_c_stmt_date') ?></label>
        <input type="date" name="statement_date" value="<?= esc(old('statement_date', date('Y-m-d'))) ?>" required>
      </div>
    </div>
    <div class="row">
      <div class="field" style="max-width:220px">
        <label><?= lang('Import.bk_opening_bank') ?></label>
        <input name="opening_balance" class="right mono" inputmode="decimal" value="<?= esc(old('opening_balance', '0')) ?>">
      </div>
      <div class="field" style="max-width:220px">
        <label><?= lang('Import.bk_closing_bank') ?></label>
        <input name="closing_balance" class="right mono" inputmode="decimal" value="<?= esc(old('closing_balance', '0')) ?>">
      </div>
      <div class="field"><label><?= lang('Import.bk_note_opt') ?></label><input name="note" value="<?= esc(old('note')) ?>"></div>
    </div>
    <div class="field">
      <label><?= lang('Import.bk_stmt_file') ?></label>
      <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
    </div>
  </div>
  <div class="card">
    <div class="btn-group">
      <button class="btn" type="submit"><?= lang('Import.upload_continue') ?></button>
      <a class="btn ghost" href="<?= site_url('banking/reconcile') ?>"><?= lang('App.cancel') ?></a>
    </div>
  </div>
</form>

<?= $this->endSection() ?>
