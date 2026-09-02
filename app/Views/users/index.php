<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1>Users</h1></div>
  <div class="btn-group"><a class="btn" href="<?= site_url('users/new') ?>">+ New User</a></div>
</div>

<div class="card">
  <table class="grid tight">
    <thead><tr><th>#</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last active</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $u): ?>
        <tr>
          <td><?= esc($u['id']) ?></td>
          <td><?= esc($u['username']) ?></td>
          <td><?= esc($u['email']) ?></td>
          <td><?= esc($u['groups']) ?: '<span class="muted">none</span>' ?></td>
          <td><?= $u['active'] ? '<span class="badge badge-green">active</span>' : '<span class="badge badge-gray">inactive</span>' ?></td>
          <td class="small muted"><?= esc($u['last']) ?></td>
          <td class="right"><a class="btn sm ghost" href="<?= site_url('users/' . $u['id'] . '/edit') ?>">Edit</a></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
