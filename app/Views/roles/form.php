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
        <label><?= lang('Setup.role_key') ?></label>
        <input name="key" class="mono" value="<?= esc($isEdit ? $key : old('key')) ?>" <?= $isEdit ? 'readonly' : 'required' ?>
          placeholder="<?= esc(lang('Setup.role_key_ph'), 'attr') ?>">
      </div>
      <div class="field">
        <label><?= lang('Setup.display_title') ?></label>
        <input name="title" value="<?= esc(old('title', $role['title'] ?? '')) ?>" required>
      </div>
    </div>
    <div class="field">
      <label><?= lang('App.description') ?></label>
      <input name="description" value="<?= esc(old('description', $role['description'] ?? '')) ?>">
    </div>

    <fieldset>
      <legend><?= lang('Setup.permissions_h') ?></legend>
      <?php
      // Group the flat catalogue by the segment before the first dot so a long
      // list (journal.*, reports.*, …) stays scannable.
$byArea    = [];
foreach ($permissions as $pk => $pl) {
    $byArea[explode('.', $pk)[0]][$pk] = $pl;
}
$areaLabel = static fn (string $a): string => $a === 'masterdata' ? 'Master data' : ucfirst($a);
?>
      <?php foreach ($byArea as $area => $perms): ?>
        <div class="perm-area" style="margin-bottom:10px">
          <div class="small muted" style="text-transform:uppercase;letter-spacing:.04em;margin-bottom:4px"><?= esc($areaLabel($area)) ?></div>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:6px">
            <?php foreach ($perms as $pk => $pl): ?>
              <label class="inline" style="font-weight:400">
                <input type="checkbox" name="perms[]" value="<?= esc($pk, 'attr') ?>" style="width:auto"
                  <?= in_array($pk, $granted, true) ? 'checked' : '' ?>>
                <span><span class="mono small"><?= esc($pk) ?></span> — <?= esc($pl) ?></span>
              </label>
            <?php endforeach ?>
          </div>
        </div>
      <?php endforeach ?>
      <?php if ($isEdit && $key === 'admin'): ?>
        <p class="small muted"><?= lang('Setup.admin_keeps_note') ?></p>
      <?php endif ?>
    </fieldset>

    <div class="btn-group">
      <button class="btn" type="submit"><?= lang('App.save') ?></button>
      <a class="btn ghost" href="<?= site_url('roles') ?>"><?= lang('App.cancel') ?></a>
    </div>
  </form>
</div>

<?= $this->endSection() ?>
