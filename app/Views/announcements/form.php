<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $isEdit = $row !== null; ?>

<div class="page-head"><h1><?= esc($title) ?></h1></div>

<div class="card" style="max-width:620px">
  <form method="post" action="<?= $isEdit ? site_url('announcements/' . $row['id']) : site_url('announcements') ?>">
    <?= csrf_field() ?>

    <div class="field">
      <label><?= lang('Announce.f_title') ?></label>
      <input name="title" maxlength="160" required value="<?= esc(old('title', $isEdit ? $row['title'] : '')) ?>">
    </div>

    <div class="field">
      <label><?= lang('Announce.f_body') ?> <span class="muted"><?= lang('App.optional') ?></span></label>
      <textarea name="body" rows="4"><?= esc(old('body', $isEdit ? $row['body'] : '')) ?></textarea>
    </div>

    <div class="field">
      <label><?= lang('Announce.f_level') ?></label>
      <?php $lvl = old('level', $isEdit ? $row['level'] : 'info'); ?>
      <select name="level" style="width:auto">
        <option value="info"    <?= $lvl === 'info' ? 'selected' : '' ?>><?= lang('Announce.lvl_info') ?></option>
        <option value="warning" <?= $lvl === 'warning' ? 'selected' : '' ?>><?= lang('Announce.lvl_warning') ?></option>
        <option value="success" <?= $lvl === 'success' ? 'selected' : '' ?>><?= lang('Announce.lvl_success') ?></option>
      </select>
    </div>

    <div class="field" style="display:flex;gap:18px;flex-wrap:wrap">
      <div>
        <label><?= lang('Announce.f_starts') ?></label>
        <input type="date" name="starts_on" value="<?= esc(old('starts_on', $isEdit ? $row['starts_on'] : '')) ?>" style="width:auto">
      </div>
      <div>
        <label><?= lang('Announce.f_ends') ?></label>
        <input type="date" name="ends_on" value="<?= esc(old('ends_on', $isEdit ? $row['ends_on'] : '')) ?>" style="width:auto">
      </div>
    </div>

    <?php $pinned = old('pinned', $isEdit ? $row['pinned'] : 0); ?>
    <?php $active = old('is_active', $isEdit ? $row['is_active'] : 1); ?>
    <label class="inline" style="font-weight:400">
      <input type="checkbox" name="pinned" value="1" style="width:auto" <?= $pinned ? 'checked' : '' ?>>
      <span><?= lang('Announce.f_pinned_hint') ?></span>
    </label>
    <label class="inline" style="font-weight:400">
      <input type="checkbox" name="is_active" value="1" style="width:auto" <?= $active ? 'checked' : '' ?>>
      <span><?= lang('Announce.f_active_hint') ?></span>
    </label>

    <div class="btn-group" style="margin-top:14px">
      <button class="btn" type="submit"><?= lang('App.save') ?></button>
      <a class="btn ghost" href="<?= site_url('announcements') ?>"><?= lang('App.cancel') ?></a>
    </div>
  </form>
</div>

<?= $this->endSection() ?>
