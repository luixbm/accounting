<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1>Custom Fields</h1><div class="muted small">Add your own fields to records — activate or hide them anytime. Values are also settable via the API.</div></div>
  <div class="btn-group"><a class="btn" href="<?= site_url('custom-fields/new?entity=' . $entity) ?>">+ New Field</a></div>
</div>

<div class="pill-nav">
  <?php foreach ($entities as $key => $label): ?>
    <a class="<?= $entity === $key ? 'active' : '' ?>" href="<?= site_url('custom-fields?entity=' . $key) ?>"><?= esc($label) ?></a>
  <?php endforeach ?>
</div>

<div class="card">
  <table class="grid tight">
    <thead><tr><th>#</th><th>Key</th><th>Label</th><th>Type</th><th>Required</th><th>In list</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($fields as $f): ?>
        <tr>
          <td class="muted"><?= (int) $f['sort_order'] ?></td>
          <td class="mono nowrap"><?= esc($f['field_key']) ?></td>
          <td><?= esc($f['label']) ?><?php if ($f['help']): ?><div class="small muted"><?= esc($f['help']) ?></div><?php endif ?></td>
          <td class="small"><?= esc($f['type']) ?></td>
          <td class="center"><?= $f['is_required'] ? '✓' : '' ?></td>
          <td class="center"><?= $f['show_in_list'] ? '✓' : '' ?></td>
          <td><?= $f['is_active'] ? '<span class="badge badge-green">active</span>' : '<span class="badge badge-gray">hidden</span>' ?></td>
          <td class="right nowrap">
            <form method="post" action="<?= site_url('custom-fields/' . $f['id'] . '/toggle') ?>" style="display:inline">
              <?= csrf_field() ?><button class="btn sm ghost" type="submit"><?= $f['is_active'] ? 'Hide' : 'Activate' ?></button>
            </form>
            <a class="btn sm ghost" href="<?= site_url('custom-fields/' . $f['id'] . '/edit') ?>">Edit</a>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (! $fields): ?><tr><td colspan="8" class="muted">No custom fields for <?= esc($entities[$entity]) ?> yet.</td></tr><?php endif ?>
    </tbody>
  </table>
</div>

<p class="muted small">Example: add a <strong>date</strong> field <code>promise_date</code> on Purchase Invoice — later a payment-list screen can sort and filter suppliers by it.</p>

<?= $this->endSection() ?>
