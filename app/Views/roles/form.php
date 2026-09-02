<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$isEdit = $key !== null;
$action = $isEdit ? site_url('roles/' . urlencode($key)) : site_url('roles');
?>

<div class="page-head"><h1><?= esc($title) ?></h1></div>

<div class="card" style="max-width:640px">
  <form method="post" action="<?= $action ?>">
    <?= csrf_field() ?>
    <div class="row">
      <div class="field" style="max-width:220px">
        <label>Role key</label>
        <input name="key" class="mono" value="<?= esc($isEdit ? $key : old('key')) ?>" <?= $isEdit ? 'readonly' : 'required' ?>
          placeholder="e.g. approver">
      </div>
      <div class="field">
        <label>Display title</label>
        <input name="title" value="<?= esc(old('title', $role['title'] ?? '')) ?>" required>
      </div>
    </div>
    <div class="field">
      <label>Description</label>
      <input name="description" value="<?= esc(old('description', $role['description'] ?? '')) ?>">
    </div>

    <fieldset>
      <legend>Permissions</legend>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:6px">
        <?php foreach ($permissions as $pk => $pl): ?>
          <label class="inline" style="font-weight:400">
            <input type="checkbox" name="perms[]" value="<?= esc($pk, 'attr') ?>" style="width:auto"
              <?= in_array($pk, $granted, true) ? 'checked' : '' ?>>
            <span><span class="mono small"><?= esc($pk) ?></span> — <?= esc($pl) ?></span>
          </label>
        <?php endforeach ?>
      </div>
      <?php if ($isEdit && $key === 'admin'): ?>
        <p class="small muted">The admin role always keeps “manage users” and “manage roles”.</p>
      <?php endif ?>
    </fieldset>

    <div class="btn-group">
      <button class="btn" type="submit">Save</button>
      <a class="btn ghost" href="<?= site_url('roles') ?>">Cancel</a>
    </div>
  </form>
</div>

<?= $this->endSection() ?>
