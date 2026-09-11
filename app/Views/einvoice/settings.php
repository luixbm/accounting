<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head"><h1><?= lang('Nav.einvoice') ?> <span class="muted small">— <?= esc(company_name()) ?></span></h1></div>

<div class="card" style="max-width:680px">
  <p class="muted small"><?= lang('Einvoice.intro') ?></p>
</div>

<form method="post" action="<?= site_url('settings/einvoice') ?>">
  <?= csrf_field() ?>

  <div class="card" style="max-width:680px">
    <h2><?= lang('Einvoice.connection_h') ?></h2>
    <label class="inline" style="font-weight:400">
      <input type="checkbox" name="enabled" value="1" style="width:auto" <?= $row['enabled'] ? 'checked' : '' ?>>
      <span><?= lang('Einvoice.enabled') ?> <span class="muted small">— <?= lang('Einvoice.enabled_hint') ?></span></span>
    </label>
    <div class="row">
      <div class="field" style="max-width:220px">
        <label><?= lang('Einvoice.environment') ?></label>
        <select name="environment">
          <option value="sandbox" <?= $row['environment'] === 'sandbox' ? 'selected' : '' ?>><?= lang('Einvoice.env_sandbox') ?></option>
          <option value="production" <?= $row['environment'] === 'production' ? 'selected' : '' ?>><?= lang('Einvoice.env_production') ?></option>
        </select>
      </div>
      <p class="muted small" style="flex-basis:100%;margin:2px 0 0"><?= lang('Einvoice.environment_hint') ?></p>
    </div>
  </div>

  <?php
  $envCard = static function (string $key, string $title, string $hint) use ($creds, $hasSecret) {
      $c = $creds[$key];
      ?>
      <div class="card" style="max-width:680px">
        <h2><?= esc($title) ?></h2>
        <p class="muted small"><?= esc($hint) ?></p>
        <div class="row">
          <div class="field"><label><?= lang('Einvoice.client_id') ?></label><input name="<?= $key ?>_client_id" value="<?= esc($c['client_id'] ?? '') ?>"></div>
          <div class="field">
            <label><?= lang('Einvoice.client_secret') ?></label>
            <input type="password" name="<?= $key ?>_client_secret" autocomplete="off" placeholder="••••••••••••">
            <div class="muted small"><?= $hasSecret[$key] ? lang('Einvoice.client_secret_set') : lang('Einvoice.client_secret_unset') ?></div>
          </div>
        </div>
        <div class="row">
          <div class="field"><label><?= lang('Einvoice.tax_id') ?></label><input name="<?= $key ?>_tax_id" value="<?= esc($c['tax_id'] ?? '') ?>"></div>
          <div class="field" style="max-width:180px">
            <label><?= lang('Einvoice.id_type') ?></label>
            <select name="<?= $key ?>_id_type">
              <?php foreach (['BRN', 'NRIC', 'PASSPORT', 'ARMY'] as $t): ?>
                <option value="<?= $t ?>" <?= ($c['id_type'] ?? 'BRN') === $t ? 'selected' : '' ?>><?= $t ?></option>
              <?php endforeach ?>
            </select>
          </div>
          <div class="field"><label><?= lang('Einvoice.id_value') ?></label><input name="<?= $key ?>_id_value" value="<?= esc($c['id_value'] ?? '') ?>"></div>
        </div>
        <div class="field" style="max-width:260px"><label><?= lang('Einvoice.sst_no') ?></label><input name="<?= $key ?>_sst_no" value="<?= esc($c['sst_no'] ?? '') ?>"></div>
      </div>
      <?php
  };
  $envCard('sandbox', lang('Einvoice.sandbox_h'), lang('Einvoice.sandbox_hint'));
  $envCard('production', lang('Einvoice.production_h'), lang('Einvoice.production_hint'));
  ?>

  <div class="card" style="max-width:680px">
    <h2><?= lang('Einvoice.profile_h') ?></h2>
    <div class="row">
      <div class="field" style="max-width:160px"><label><?= lang('Einvoice.msic_code') ?></label><input name="msic_code" value="<?= esc($row['msic_code'] ?? '') ?>"></div>
      <div class="field"><label><?= lang('Einvoice.business_activity') ?></label><input name="business_activity" value="<?= esc($row['business_activity'] ?? '') ?>"></div>
    </div>
  </div>

  <div class="card" style="max-width:680px">
    <h2><?= lang('Einvoice.address_h') ?></h2>
    <div class="field"><label><?= lang('Einvoice.addr_line1') ?></label><input name="addr_line1" value="<?= esc($row['addr_line1'] ?? '') ?>"></div>
    <div class="field"><label><?= lang('Einvoice.addr_line2') ?></label><input name="addr_line2" value="<?= esc($row['addr_line2'] ?? '') ?>"></div>
    <div class="row">
      <div class="field"><label><?= lang('Einvoice.addr_city') ?></label><input name="addr_city" value="<?= esc($row['addr_city'] ?? '') ?>"></div>
      <div class="field" style="max-width:140px"><label><?= lang('Einvoice.addr_postcode') ?></label><input name="addr_postcode" value="<?= esc($row['addr_postcode'] ?? '') ?>"></div>
      <div class="field" style="max-width:140px"><label><?= lang('Einvoice.addr_state') ?></label><input name="addr_state" value="<?= esc($row['addr_state'] ?? '') ?>"></div>
      <div class="field" style="max-width:140px"><label><?= lang('Einvoice.addr_country') ?></label><input name="addr_country" value="<?= esc($row['addr_country'] ?? 'MYS') ?>"></div>
    </div>
  </div>

  <div class="card" style="max-width:680px">
    <h2><?= lang('Einvoice.contact_h') ?></h2>
    <div class="row">
      <div class="field"><label><?= lang('Einvoice.contact_phone') ?></label><input name="contact_phone" value="<?= esc($row['contact_phone'] ?? '') ?>"></div>
      <div class="field"><label><?= lang('Einvoice.contact_email') ?></label><input type="email" name="contact_email" value="<?= esc($row['contact_email'] ?? '') ?>"></div>
    </div>
  </div>

  <div class="card"><button class="btn" type="submit"><?= lang('Setup.save_settings') ?></button></div>
</form>

<form method="post" action="<?= site_url('settings/einvoice/test') ?>">
  <?= csrf_field() ?>
  <div class="card" style="max-width:680px">
    <h2><?= lang('Einvoice.test_h') ?></h2>
    <p class="muted small"><?= lang('Einvoice.test_hint') ?></p>
    <button class="btn ghost" type="submit"><?= lang('Einvoice.test_btn') ?></button>
  </div>
</form>

<?= $this->endSection() ?>
