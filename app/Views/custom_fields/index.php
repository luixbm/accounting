<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1><?= lang('Setup.cf_h') ?></h1><div class="muted small"><?= lang('Setup.cf_note') ?></div></div>
  <div class="btn-group"><a class="btn" href="<?= site_url('custom-fields/new?entity=' . $entity) ?>"><?= lang('Setup.new_field') ?></a></div>
</div>

<div class="pill-nav">
  <?php foreach ($entities as $key => $label): ?>
    <a class="<?= $entity === $key ? 'active' : '' ?>" href="<?= site_url('custom-fields?entity=' . $key) ?>"><?= esc($label) ?></a>
  <?php endforeach ?>
</div>

<div class="card">
  <table class="grid tight">
    <thead><tr><th>#</th><th><?= lang('Setup.cf_key') ?></th><th><?= lang('Setup.cf_label') ?></th><th><?= lang('Report.col_type') ?></th><th><?= lang('Setup.cf_required') ?></th><th><?= lang('Setup.cf_in_list') ?></th><th><?= lang('App.status') ?></th><th></th></tr></thead>
    <tbody>
      <?php foreach ($fields as $f): ?>
        <tr>
          <td class="muted"><?= (int) $f['sort_order'] ?></td>
          <td class="mono nowrap"><?= esc($f['field_key']) ?></td>
          <td><?= esc($f['label']) ?><?php if ($f['help']): ?><div class="small muted"><?= esc($f['help']) ?></div><?php endif ?></td>
          <td class="small"><?= esc($f['type']) ?></td>
          <td class="center"><?= $f['is_required'] ? '✓' : '' ?></td>
          <td class="center"><?= $f['show_in_list'] ? '✓' : '' ?></td>
          <td><?= $f['is_active'] ? '<span class="badge badge-green">' . esc(lang('App.active')) . '</span>' : '<span class="badge badge-gray">' . esc(lang('App.inactive')) . '</span>' ?></td>
          <td class="right nowrap">
            <form method="post" action="<?= site_url('custom-fields/' . $f['id'] . '/toggle') ?>" style="display:inline">
              <?= csrf_field() ?><button class="btn sm ghost" type="submit"><?= $f['is_active'] ? lang('Setup.cf_hide') : lang('Setup.cf_activate') ?></button>
            </form>
            <a class="btn sm ghost" href="<?= site_url('custom-fields/' . $f['id'] . '/edit') ?>"><?= lang('App.edit') ?></a>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (! $fields): ?><tr><td colspan="8" class="muted"><?= lang('Setup.cf_none_yet', [esc($entities[$entity])]) ?></td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<p class="muted small"><?= lang('Setup.cf_example') ?></p>

<?= $this->endSection() ?>
