<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head"><h1><?= lang('Nav.profile') ?></h1></div>

<div class="card" style="max-width:520px">
  <div class="field">
    <label><?= lang('Profile.account') ?></label>
    <p style="margin:0"><strong><?= esc($user->username ?? $user->email) ?></strong>
      <span class="muted small"><?= esc($user->email) ?></span></p>
  </div>

  <form method="post" action="<?= site_url('profile') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="field">
      <label><?= lang('Profile.photo') ?></label>
      <div class="avatar-field">
        <?= user_avatar_tag(null, 'avatar-lg') ?>
        <div>
          <input type="file" name="photo" accept="image/png,image/jpeg,image/webp,image/gif">
          <p class="small muted"><?= lang('Profile.photo_hint') ?></p>
          <?php if (user_avatar_url()): ?>
            <label class="inline small" style="font-weight:400">
              <input type="checkbox" name="remove_photo" value="1" style="width:auto"> <?= lang('Profile.remove_photo') ?>
            </label>
          <?php endif ?>
        </div>
      </div>
    </div>
    <div class="btn-group" style="margin-top:14px">
      <button class="btn" type="submit"><?= lang('App.save') ?></button>
    </div>
  </form>
</div>

<?= $this->endSection() ?>
