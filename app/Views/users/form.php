<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $isEdit = $user !== null; ?>

<div class="page-head"><h1><?= esc($title) ?></h1></div>

<div class="card" style="max-width:520px">
  <form method="post" action="<?= $isEdit ? site_url('users/' . $user->id) : site_url('users') ?>">
    <?= csrf_field() ?>

    <div class="field">
      <label>Username</label>
      <input name="username" value="<?= esc(old('username', $isEdit ? $user->username : '')) ?>" <?= $isEdit ? 'readonly' : 'required' ?>>
    </div>
    <div class="field">
      <label>Email</label>
      <input name="email" type="email" value="<?= esc(old('email', $isEdit ? $user->email : '')) ?>" <?= $isEdit ? 'readonly' : 'required' ?>>
    </div>
    <div class="field">
      <label>Password <?= $isEdit ? '(leave blank to keep current)' : '' ?></label>
      <input name="password" type="password" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
    </div>
    <fieldset>
      <legend>Roles</legend>
      <?php foreach ($roles as $key => $title): ?>
        <label class="inline" style="font-weight:400">
          <input type="checkbox" name="roles[]" value="<?= esc($key, 'attr') ?>" style="width:auto"
            <?= in_array($key, $userRoles, true) ? 'checked' : '' ?>>
          <span><?= esc($title) ?> <span class="muted mono small"><?= esc($key) ?></span></span>
        </label>
      <?php endforeach ?>
      <p class="small muted"><a href="<?= site_url('roles') ?>">Manage roles &amp; permissions</a></p>
    </fieldset>
    <?php if ($isEdit): ?>
      <label class="inline" style="font-weight:400">
        <input type="checkbox" name="active" value="1" style="width:auto" <?= $user->active ? 'checked' : '' ?>> Active
      </label>
    <?php endif ?>

    <div class="btn-group" style="margin-top:14px">
      <button class="btn" type="submit">Save</button>
      <a class="btn ghost" href="<?= site_url('users') ?>">Cancel</a>
    </div>
  </form>
</div>

<?= $this->endSection() ?>
