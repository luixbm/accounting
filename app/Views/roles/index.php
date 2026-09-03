<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1><?= lang('Setup.roles_h') ?></h1><div class="muted small"><?= lang('Setup.roles_note') ?></div></div>
  <div class="btn-group"><a class="btn" href="<?= site_url('roles/new') ?>"><?= lang('Setup.new_role') ?></a></div>
</div>

<div class="card" style="overflow-x:auto">
  <table class="grid tight">
    <thead>
      <tr>
        <th><?= lang('Setup.ctl_role') ?></th>
        <?php foreach ($permissions as $key => $label): ?>
          <th class="center" style="writing-mode:vertical-rl;transform:rotate(180deg);white-space:nowrap;font-weight:600" title="<?= esc($label) ?>"><?= esc($key) ?></th>
        <?php endforeach ?>
        <th class="center"><?= lang('Setup.col_users') ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($groups as $key => $g): ?>
        <?php $perms = $matrix[$key] ?? []; ?>
        <tr>
          <td>
            <strong><?= esc($g['title'] ?? $key) ?></strong>
            <div class="small muted mono"><?= esc($key) ?></div>
          </td>
          <?php foreach ($permissions as $pk => $pl): ?>
            <td class="center">
              <?php $has = in_array($pk, $perms, true) || in_array($pk . '.*', $perms, true) || in_array('*', $perms, true); ?>
              <?= $has ? '<span style="color:var(--green);font-weight:700">✓</span>' : '<span class="muted">·</span>' ?>
            </td>
          <?php endforeach ?>
          <td class="center"><?= $counts[$key] ?? 0 ?></td>
          <td class="right nowrap">
            <a class="btn sm ghost" href="<?= site_url('roles/' . urlencode($key) . '/edit') ?>"><?= lang('App.edit') ?></a>
            <?php if ($key !== 'admin' && ($counts[$key] ?? 0) === 0): ?>
              <form method="post" action="<?= site_url('roles/' . urlencode($key) . '/delete') ?>" style="display:inline"
                onsubmit="return confirm('<?= esc(lang('Setup.delete_role_confirm', [esc($key)]), 'js') ?>')">
                <?= csrf_field() ?><button class="btn sm danger" type="submit"><?= lang('App.delete') ?></button>
              </form>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h2><?= lang('Setup.permissions_h') ?></h2>
  <table class="grid tight">
    <tbody>
      <?php foreach ($permissions as $key => $label): ?>
        <tr><td class="mono nowrap"><?= esc($key) ?></td><td><?= esc($label) ?></td></tr>
      <?php endforeach ?>
    </tbody>
  </table>
  <p class="muted small"><?= lang('Setup.perm_set_note') ?></p>
</div>

<?= $this->endSection() ?>
