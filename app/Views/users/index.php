<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1><?= lang('Nav.users') ?></h1></div>
  <div class="btn-group"><a class="btn" href="<?= site_url('users/new') ?>"><?= lang('Setup.new_user') ?></a></div>
</div>

<div class="card">
  <table class="grid tight">
    <thead><tr><th>#</th><th></th><th><?= lang('Setup.username') ?></th><th>Email</th><th><?= lang('Setup.role') ?></th><th><?= lang('App.status') ?></th><th><?= lang('Setup.last_active') ?></th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $u): ?>
        <tr>
          <td><?= esc($u['id']) ?></td>
          <td class="avatar-cell"><?= $u['avatar'] ?></td>
          <td><?= esc($u['username']) ?></td>
          <td><?= esc($u['email']) ?></td>
          <td><?= esc($u['groups']) ?: '<span class="muted">' . esc(lang('Setup.no_role')) . '</span>' ?></td>
          <td><?= $u['active'] ? '<span class="badge badge-green">' . esc(lang('App.active')) . '</span>' : '<span class="badge badge-gray">' . esc(lang('App.inactive')) . '</span>' ?></td>
          <td class="small muted"><?= esc($u['last']) ?></td>
          <td class="right"><a class="btn sm ghost" href="<?= site_url('users/' . $u['id'] . '/edit') ?>"><?= lang('App.edit') ?></a></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
