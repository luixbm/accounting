<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$v      = static fn (string $k, $d = '') => old($k, $company[$k] ?? $d);
$action = $company ? site_url('companies/' . $company['id']) : site_url('companies');
$logo   = $company && $company['logo_path'] && is_file(FCPATH . $company['logo_path']) ? base_url($company['logo_path']) : null;
?>

<div class="page-head"><h1><?= esc($title) ?></h1></div>

<div class="card" style="max-width:660px">
  <form method="post" action="<?= $action ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="row">
      <div class="field" style="max-width:160px"><label>Code</label><input name="code" class="mono" value="<?= esc($v('code')) ?>" required></div>
      <div class="field"><label>Display name</label><input name="name" value="<?= esc($v('name')) ?>" required></div>
    </div>
    <div class="field"><label>Legal name</label><input name="legal_name" value="<?= esc($v('legal_name')) ?>"></div>
    <div class="row">
      <div class="field" style="max-width:260px"><label>NPWP</label><input name="npwp" class="mono" value="<?= esc($v('npwp')) ?>"></div>
      <div class="field" style="max-width:200px">
        <label>Base currency</label>
        <?php $bc = $v('base_currency', 'IDR'); ?>
        <select name="base_currency" <?= $company ? 'disabled title="Base currency is fixed once the company has data"' : '' ?>>
          <?php foreach ($currencies as $c): ?>
            <option value="<?= esc($c['code'], 'attr') ?>" <?= $bc === $c['code'] ? 'selected' : '' ?>><?= esc($c['code'] . ' — ' . $c['name']) ?></option>
          <?php endforeach ?>
        </select>
        <?php if ($company): ?><input type="hidden" name="base_currency" value="<?= esc($bc) ?>"><?php endif ?>
        <span class="small muted">The company's functional / reporting currency.</span>
      </div>
    </div>
    <div class="field"><label>Address</label><textarea name="address" rows="2"><?= esc($v('address')) ?></textarea></div>

    <div class="field">
      <label>Logo</label>
      <?php if ($logo): ?>
        <div class="inline" style="margin-bottom:8px">
          <img src="<?= esc($logo) ?>" style="max-height:40px;max-width:150px;border:1px solid var(--line);border-radius:6px;padding:4px;background:var(--panel)">
          <label class="inline" style="font-weight:400"><input type="checkbox" name="remove_logo" value="1" style="width:auto"> Remove</label>
        </div>
      <?php endif ?>
      <input type="file" name="logo" accept=".png,.jpg,.jpeg,.gif,.webp,.svg">
    </div>

    <label class="inline" style="font-weight:400">
      <input type="checkbox" name="is_active" value="1" style="width:auto" <?= old('is_active', $company['is_active'] ?? 1) ? 'checked' : '' ?>> Active
    </label>

    <div class="btn-group" style="margin-top:14px">
      <button class="btn" type="submit">Save</button>
      <a class="btn ghost" href="<?= site_url('companies') ?>">Cancel</a>
    </div>
  </form>
</div>

<?= $this->endSection() ?>
