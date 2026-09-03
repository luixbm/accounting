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

<div class="page-head"><h1><?= lang('Nav.settings') ?></h1></div>

<div class="card" style="max-width:680px">
  <h2><?= lang('Setup.company_profile_h') ?></h2>
  <p class="muted small"><?= lang('Setup.company_profile_note', ['<strong>' . esc(company_name()) . '</strong>']) ?></p>
</div>

<form method="post" action="<?= site_url('settings') ?>">
  <?= csrf_field() ?>

  <div class="card" style="max-width:680px">
    <h2><?= lang('Setup.appearance_h') ?></h2>
    <div class="row">
      <div class="field" style="max-width:260px">
        <label><?= lang('Setup.default_theme') ?> <span class="muted"><?= lang('Setup.users_switch_own') ?></span></label>
        <select name="theme">
          <?php foreach (['light' => lang('Setup.theme_light'), 'green' => lang('Setup.theme_green'), 'blue' => lang('Setup.theme_blue'), 'dark' => lang('Setup.theme_dark')] as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= $current['theme'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:240px">
        <label><?= lang('Setup.default_language') ?> <span class="muted"><?= lang('Setup.users_switch_own') ?></span></label>
        <select name="locale">
          <?php foreach (['id' => 'Bahasa Indonesia', 'en' => 'English'] as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= $current['locale'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach ?>
        </select>
      </div>
    </div>
  </div>

  <div class="card" style="max-width:680px">
    <h2><?= lang('Setup.settings_ctl_h') ?> <span class="muted small">— <?= esc(company_name()) ?></span></h2>
    <p class="muted small"><?= lang('Setup.settings_ctl_note') ?></p>
    <div class="field"><label><?= lang('Setup.retained_earnings') ?></label><?= $acctSelect('retainedEarningsCode', $current['retainedEarningsCode']) ?></div>
    <div class="field"><label><?= lang('Setup.rounding_diff') ?></label><?= $acctSelect('roundingCode', $current['roundingCode']) ?></div>
  </div>

  <div class="card" style="max-width:680px">
    <h2><?= lang('Setup.tax_h') ?> <span class="muted small">— <?= esc(company_name()) ?></span></h2>
    <p class="muted small"><?= lang('Setup.tax_used_by') ?></p>
    <div class="row">
      <div class="field" style="max-width:120px"><label><?= lang('Setup.ppn_rate') ?></label><input name="ppnRate" type="number" step="any" value="<?= esc($current['ppnRate']) ?>"></div>
      <div class="field"><label><?= lang('Setup.ppn_input') ?></label><?= $acctSelect('ppnInputCode', $current['ppnInputCode']) ?></div>
    </div>
    <div class="field"><label><?= lang('Setup.ppn_output') ?></label><?= $acctSelect('ppnOutputCode', $current['ppnOutputCode']) ?></div>
    <div class="row">
      <div class="field" style="max-width:120px"><label><?= lang('Setup.pph_rate') ?></label><input name="pph23Rate" type="number" step="any" value="<?= esc($current['pph23Rate']) ?>"></div>
      <div class="field"><label><?= lang('Setup.pph_payable') ?> <span class="muted"><?= lang('Setup.pph_payable_note') ?></span></label><?= $acctSelect('pph23PayableCode', $current['pph23PayableCode']) ?></div>
    </div>
    <div class="field"><label><?= lang('Setup.pph_prepaid') ?> <span class="muted"><?= lang('Setup.pph_prepaid_note') ?></span></label><?= $acctSelect('pph23PrepaidCode', $current['pph23PrepaidCode']) ?></div>
  </div>

  <div class="card"><button class="btn" type="submit"><?= lang('Setup.save_settings') ?></button></div>
</form>

<?= $this->endSection() ?>
