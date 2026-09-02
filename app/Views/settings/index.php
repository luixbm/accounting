<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$acctSelect = static function (string $name, string $value) use ($accounts): string {
    $h = '<select name="' . $name . '"><option value="">— none —</option>';
    foreach ($accounts as $a) {
        if ((int) $a['is_group'] === 1) {
            continue;
        }
        $sel = $value === $a['code'] ? ' selected' : '';
        $h .= '<option value="' . esc($a['code'], 'attr') . '"' . $sel . '>' . esc($a['code'] . ' · ' . $a['name']) . '</option>';
    }

    return $h . '</select>';
};
?>

<div class="page-head"><h1>Settings</h1></div>

<div class="card" style="max-width:680px">
  <h2>Company profile</h2>
  <p class="muted small">
    Name, NPWP, address and logo now live per company under
    <a href="<?= site_url('companies') ?>">Companies</a>.
    You are currently working in <strong><?= esc(company_name()) ?></strong>.
  </p>
</div>

<form method="post" action="<?= site_url('settings') ?>">
  <?= csrf_field() ?>

  <div class="card" style="max-width:680px">
    <h2>Appearance</h2>
    <div class="row">
      <div class="field" style="max-width:260px">
        <label>Default theme <span class="muted">(users can switch their own)</span></label>
        <select name="theme">
          <?php foreach (['light' => 'Modern light', 'green' => 'Green', 'blue' => 'Blue', 'dark' => 'Dark'] as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= $current['theme'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:240px">
        <label>Default language <span class="muted">(users can switch their own)</span></label>
        <select name="locale">
          <?php foreach (['id' => 'Bahasa Indonesia', 'en' => 'English'] as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= $current['locale'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach ?>
        </select>
      </div>
    </div>
  </div>

  <div class="card" style="max-width:680px">
    <h2>Control accounts <span class="muted small">— for <?= esc(company_name()) ?></span></h2>
    <p class="muted small">
      Trade A/R, trade A/P and realized FX gain/loss are set <b>per currency</b> under
      <a href="<?= site_url('control-accounts') ?>">Setup &rarr; Control Accounts</a>.
      The two below are single accounts.
    </p>
    <div class="field"><label>Retained Earnings</label><?= $acctSelect('retainedEarningsCode', $current['retainedEarningsCode']) ?></div>
    <div class="field"><label>Rounding difference</label><?= $acctSelect('roundingCode', $current['roundingCode']) ?></div>
  </div>

  <div class="card" style="max-width:680px">
    <h2>Tax <span class="muted small">— for <?= esc(company_name()) ?></span></h2>
    <p class="muted small">Used by the Purchase &amp; Sales modules.</p>
    <div class="row">
      <div class="field" style="max-width:120px"><label>PPN rate %</label><input name="ppnRate" type="number" step="any" value="<?= esc($current['ppnRate']) ?>"></div>
      <div class="field"><label>PPN Masukan (input VAT)</label><?= $acctSelect('ppnInputCode', $current['ppnInputCode']) ?></div>
    </div>
    <div class="field"><label>PPN Keluaran (output VAT)</label><?= $acctSelect('ppnOutputCode', $current['ppnOutputCode']) ?></div>
    <div class="row">
      <div class="field" style="max-width:120px"><label>PPh 23 rate %</label><input name="pph23Rate" type="number" step="any" value="<?= esc($current['pph23Rate']) ?>"></div>
      <div class="field"><label>Hutang PPh 23 <span class="muted">(we withhold on purchases)</span></label><?= $acctSelect('pph23PayableCode', $current['pph23PayableCode']) ?></div>
    </div>
    <div class="field"><label>Uang Muka PPh 23 <span class="muted">(customer withholds on our sales)</span></label><?= $acctSelect('pph23PrepaidCode', $current['pph23PrepaidCode']) ?></div>
  </div>

  <div class="card"><button class="btn" type="submit">Save settings</button></div>
</form>

<?= $this->endSection() ?>
