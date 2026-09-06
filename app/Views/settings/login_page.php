<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$styles = ['luxury', 'simple'];
$events = ['default', 'independence', 'nyepi', 'eid', 'christmas', 'custom'];
$anims  = ['full', 'light', 'off'];
$accent = $cfg['accent'] !== '' ? $cfg['accent'] : '#D4AF37';
?>

<div class="page-head">
  <div><h1><?= lang('LoginPage.title') ?></h1><div class="muted small"><?= lang('LoginPage.sub') ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('login') ?>" target="_blank" rel="noopener"><?= lang('LoginPage.preview') ?></a></div>
</div>

<form method="post" action="<?= site_url('settings/login-page') ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <div class="card" style="max-width:720px">
    <div class="field" style="max-width:260px">
      <label><?= lang('LoginPage.f_style') ?></label>
      <select name="style">
        <?php foreach ($styles as $s): ?>
          <option value="<?= $s ?>" <?= $cfg['style'] === $s ? 'selected' : '' ?>><?= lang('LoginPage.style_' . $s) ?></option>
        <?php endforeach ?>
      </select>
      <div class="small muted"><?= lang('LoginPage.style_hint') ?></div>
    </div>
  </div>

  <div class="card" style="max-width:720px">
    <h2><?= lang('LoginPage.h_theme') ?> <span class="muted small">— <?= lang('LoginPage.luxury_only') ?></span></h2>
    <div class="row">
      <div class="field" style="max-width:220px">
        <label><?= lang('LoginPage.f_event') ?></label>
        <select name="event">
          <?php foreach ($events as $e): ?>
            <option value="<?= $e ?>" <?= $cfg['event'] === $e ? 'selected' : '' ?>><?= lang('LoginPage.event_' . $e) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:200px">
        <label><?= lang('LoginPage.f_animation') ?></label>
        <select name="animation">
          <?php foreach ($anims as $a): ?>
            <option value="<?= $a ?>" <?= $cfg['animation'] === $a ? 'selected' : '' ?>><?= lang('LoginPage.anim_' . $a) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:200px">
        <label><?= lang('LoginPage.f_accent') ?></label>
        <div class="inline" style="gap:10px">
          <input type="color" name="accent" value="<?= esc($accent) ?>" style="width:52px;height:38px;padding:2px">
          <label class="inline" style="font-weight:400;gap:6px">
            <input type="checkbox" name="_accent_preset" value="1" onchange="if(this.checked)this.form.accent.value='<?= esc($accent) ?>';"
              style="width:auto"> <span class="small"><?= lang('LoginPage.accent_preset') ?></span>
          </label>
        </div>
        <div class="small muted"><?= lang('LoginPage.accent_hint') ?></div>
      </div>
    </div>

    <div class="row">
      <div class="field">
        <label><?= lang('LoginPage.f_bg') ?></label>
        <input type="file" name="bg_image" accept="image/png,image/jpeg,image/webp">
        <?php if ($cfg['bgImage'] !== '' && is_file(FCPATH . $cfg['bgImage'])): ?>
          <div class="inline" style="gap:10px;margin-top:8px">
            <img src="<?= base_url($cfg['bgImage']) ?>" alt="" style="height:54px;border-radius:6px;border:1px solid var(--line)">
            <label class="inline" style="font-weight:400;gap:6px"><input type="checkbox" name="remove_bg" value="1" style="width:auto"> <span class="small"><?= lang('LoginPage.remove_bg') ?></span></label>
          </div>
        <?php endif ?>
        <div class="small muted"><?= lang('LoginPage.bg_hint') ?></div>
      </div>
    </div>
  </div>

  <div class="card" style="max-width:720px">
    <h2><?= lang('LoginPage.h_copy') ?></h2>
    <div class="row">
      <div class="field"><label><?= lang('LoginPage.f_brand') ?></label>
        <input name="brand" maxlength="60" value="<?= esc($cfg['brand']) ?>" placeholder="LuixSpace">
        <div class="small muted"><?= lang('LoginPage.brand_hint') ?></div>
      </div>
      <div class="field" style="max-width:120px"><label><?= lang('LoginPage.f_mark') ?></label>
        <input name="mark" maxlength="2" value="<?= esc($cfg['mark']) ?>" placeholder="LX">
      </div>
      <div class="field"><label><?= lang('LoginPage.f_tagline') ?></label>
        <input name="tagline" maxlength="80" value="<?= esc($cfg['tagline']) ?>">
      </div>
    </div>
    <div class="field"><label><?= lang('LoginPage.f_headline') ?></label>
      <textarea name="headline" rows="2" style="width:100%"><?= esc($cfg['headline']) ?></textarea>
      <div class="small muted"><?= lang('LoginPage.headline_hint') ?></div>
    </div>
    <div class="field"><label><?= lang('LoginPage.f_subtitle') ?></label>
      <textarea name="subtitle" rows="2" style="width:100%"><?= esc($cfg['subtitle']) ?></textarea>
      <div class="small muted"><?= lang('LoginPage.subtitle_hint') ?></div>
    </div>
    <div class="field"><label><?= lang('LoginPage.f_footer') ?></label>
      <input name="footer" maxlength="120" value="<?= esc($cfg['footer']) ?>" placeholder="© <?= date('Y') ?> <?= esc(company_name(), 'attr') ?>">
    </div>
  </div>

  <div class="card" style="max-width:720px">
    <div class="btn-group">
      <button class="btn" type="submit"><?= lang('App.save') ?></button>
      <a class="btn ghost" href="<?= site_url('login') ?>" target="_blank" rel="noopener"><?= lang('LoginPage.preview') ?></a>
    </div>
    <p class="small muted" style="margin-top:8px"><?= lang('LoginPage.banner_note', ['<a href="' . site_url('announcements') . '">' . lang('Nav.announcements') . '</a>']) ?></p>
  </div>
</form>

<?= $this->endSection() ?>
