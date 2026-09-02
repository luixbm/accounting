<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head"><h1>Bank Transfer</h1></div>

<div class="card" style="max-width:620px">
  <form method="post" action="<?= site_url('banking/transfer') ?>">
    <?= csrf_field() ?>
    <div class="row">
      <div class="field">
        <label>From account</label>
        <select name="from_account" required>
          <option value="">— choose —</option>
          <?php foreach ($banks as $b): ?><option value="<?= $b['id'] ?>" <?= old('from_account') == $b['id'] ? 'selected' : '' ?>><?= esc($b['code'] . ' · ' . $b['name']) ?></option><?php endforeach ?>
        </select>
      </div>
      <div class="field">
        <label>To account</label>
        <select name="to_account" required>
          <option value="">— choose —</option>
          <?php foreach ($banks as $b): ?><option value="<?= $b['id'] ?>" <?= old('to_account') == $b['id'] ? 'selected' : '' ?>><?= esc($b['code'] . ' · ' . $b['name']) ?></option><?php endforeach ?>
        </select>
      </div>
    </div>
    <div class="row">
      <div class="field" style="max-width:170px"><label>Date</label><input type="date" name="date" value="<?= esc(old('date', date('Y-m-d'))) ?>" required></div>
      <div class="field" style="max-width:220px"><label>Amount (<?= base_code() ?>)</label><input name="amount" class="mono right" inputmode="decimal" value="<?= esc(old('amount')) ?>" required></div>
      <div class="field"><label>Reference</label><input name="reference" value="<?= esc(old('reference')) ?>"></div>
    </div>
    <fieldset>
      <legend>Bank charge (optional)</legend>
      <div class="row">
        <div class="field" style="max-width:200px"><label>Fee (<?= base_code() ?>)</label><input name="fee" class="mono right" inputmode="decimal" value="<?= esc(old('fee')) ?>"></div>
        <div class="field">
          <label>Fee account</label>
          <select name="fee_account">
            <option value="">—</option>
            <?php foreach ($feeAccts as $a): ?><option value="<?= $a['id'] ?>" <?= old('fee_account') == $a['id'] ? 'selected' : '' ?>><?= esc($a['code'] . ' · ' . $a['name']) ?></option><?php endforeach ?>
          </select>
        </div>
      </div>
      <p class="small muted">The fee is deducted from the “from” account.</p>
    </fieldset>
    <div class="field"><label>Memo</label><input name="memo" value="<?= esc(old('memo')) ?>"></div>
    <div class="btn-group">
      <button class="btn" type="submit">Post transfer</button>
      <a class="btn ghost" href="<?= site_url('banking') ?>">Cancel</a>
    </div>
  </form>
</div>

<?= $this->endSection() ?>
