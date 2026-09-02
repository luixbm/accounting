<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $s = $parsed['summary']; ?>

<div class="page-head">
  <div><h1>Import COA · Preview</h1><div class="muted small"><?= esc($batch['filename']) ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('accounts/import/' . $batch['id'] . '/map') ?>">Back to mapping</a></div>
</div>
<?= view('accounts/import/_steps', ['active' => 'preview', 'batch' => $batch]) ?>

<div class="card">
  <div class="row" style="gap:26px">
    <div><div class="muted small">Rows</div><div class="mono" style="font-size:1.2rem"><?= $s['total'] ?></div></div>
    <div><div class="muted small">New</div><div class="mono" style="font-size:1.2rem"><?= $s['new'] ?></div></div>
    <div><div class="muted small">Update</div><div class="mono" style="font-size:1.2rem"><?= $s['update'] ?></div></div>
    <div><div class="muted small">Group headers</div><div class="mono" style="font-size:1.2rem"><?= $s['groups'] ?></div></div>
    <div><div class="muted small">Errors</div><div class="mono" style="font-size:1.2rem;<?= $s['error'] ? 'color:var(--red)' : '' ?>"><?= $s['error'] ?></div></div>
  </div>
</div>

<?php if ($s['error']): ?>
  <div class="alert alert-error">Rows with errors are skipped on commit. Fix them in the file and re-import, or continue and add them later.</div>
<?php endif ?>

<div class="card">
  <div style="overflow-x:auto">
    <table class="grid tight">
      <thead><tr><th>#</th><th>Code</th><th>Name</th><th>File type</th><th>App type</th><th>Cash</th><th>Group</th><th>Parent</th><th>Cur</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($parsed['rows'] as $r): ?>
          <tr<?= $r['errors'] ? ' style="background:var(--red-bg)"' : '' ?>>
            <td class="muted"><?= $r['n'] ?></td>
            <td class="mono nowrap"><?= esc($r['code']) ?></td>
            <td><?= esc($r['name']) ?></td>
            <td class="mono small"><?= esc($r['raw_type']) ?></td>
            <td class="small"><?= esc($r['type']) ?></td>
            <td class="small"><?= $r['is_cash'] ? '✓' : '' ?></td>
            <td class="small"><?= $r['is_group'] ? '✓' : '' ?></td>
            <td class="mono small"><?= esc($r['parent_code']) ?></td>
            <td class="small"><?= esc($r['currency']) ?></td>
            <td>
              <?= $r['errors']
                ? '<span class="badge badge-red">' . esc(implode(' ', $r['errors'])) . '</span>'
                : '<span class="badge badge-gray">' . esc($r['action']) . '</span>' ?>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <form method="post" action="<?= site_url('accounts/import/' . $batch['id'] . '/commit') ?>" onsubmit="return confirm('Import <?= $s['new'] ?> new and update <?= $s['update'] ?> existing accounts?')">
    <?= csrf_field() ?>
    <div class="btn-group">
      <button class="btn" type="submit">Commit import</button>
      <a class="btn ghost" href="<?= site_url('accounts/import') ?>">Cancel</a>
      <span class="muted small" style="align-self:center"><?= $s['ok'] ?> of <?= $s['total'] ?> rows will be written.</span>
    </div>
  </form>
</div>

<?= $this->endSection() ?>
