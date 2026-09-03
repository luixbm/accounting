<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head"><h1><?= lang('Txn.bank_transfer_h') ?></h1></div>

<div class="card" style="max-width:620px">
  <form method="post" action="<?= site_url('banking/transfer') ?>">
    <?= csrf_field() ?>
    <div class="row">
      <div class="field">
        <label><?= lang('Txn.from_account') ?></label>
        <select name="from_account" required>
          <option value=""><?= lang('App.choose') ?></option>
          <?php foreach ($banks as $b): ?><option value="<?= $b['id'] ?>" <?= old('from_account') == $b['id'] ? 'selected' : '' ?>><?= esc($b['code'] . ' · ' . $b['name']) ?></option><?php endforeach ?>
        </select>
      </div>
      <div class="field">
        <label><?= lang('Txn.to_account') ?></label>
        <select name="to_account" required>
          <option value=""><?= lang('App.choose') ?></option>
          <?php foreach ($banks as $b): ?><option value="<?= $b['id'] ?>" <?= old('to_account') == $b['id'] ? 'selected' : '' ?>><?= esc($b['code'] . ' · ' . $b['name']) ?></option><?php endforeach ?>
        </select>
      </div>
    </div>
    <div class="row">
      <div class="field" style="max-width:170px"><label><?= lang('App.date') ?></label><input type="date" name="date" value="<?= esc(old('date', date('Y-m-d'))) ?>" required></div>
      <div class="field" style="max-width:220px"><label><?= lang('App.amount') ?> (<?= base_code() ?>)</label><input name="amount" class="mono right" inputmode="decimal" value="<?= esc(old('amount')) ?>" required></div>
      <div class="field"><label><?= lang('App.reference') ?></label><input name="reference" value="<?= esc(old('reference')) ?>"></div>
    </div>
    <fieldset>
      <legend><?= lang('Txn.bank_charge_optional') ?></legend>
      <div class="row">
        <div class="field" style="max-width:200px"><label><?= lang('Txn.fee') ?> (<?= base_code() ?>)</label><input name="fee" class="mono right" inputmode="decimal" value="<?= esc(old('fee')) ?>"></div>
        <div class="field">
          <label><?= lang('Txn.fee_account') ?></label>
          <select name="fee_account">
            <option value="">—</option>
            <?php foreach ($feeAccts as $a): ?><option value="<?= $a['id'] ?>" <?= old('fee_account') == $a['id'] ? 'selected' : '' ?>><?= esc($a['code'] . ' · ' . $a['name']) ?></option><?php endforeach ?>
          </select>
        </div>
      </div>
      <p class="small muted"><?= lang('Txn.fee_deducted_note') ?></p>
    </fieldset>
    <div class="field"><label><?= lang('App.memo') ?></label><input name="memo" value="<?= esc(old('memo')) ?>"></div>
    <div class="btn-group">
      <button class="btn" type="submit"><?= lang('Txn.post_transfer') ?></button>
      <a class="btn ghost" href="<?= site_url('banking') ?>"><?= lang('App.cancel') ?></a>
    </div>
  </form>
</div>

<?= $this->endSection() ?>
