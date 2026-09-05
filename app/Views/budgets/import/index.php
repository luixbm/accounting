<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div>
    <h1><?= lang('Budget.imp_title') ?></h1>
    <div class="muted small"><?= lang('Budget.imp_into', [esc($ver['name']) . ' (' . (int) $ver['year'] . ')']) ?></div>
  </div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('budgets/' . $ver['id']) ?>"><?= lang('App.back') ?></a></div>
</div>

<div class="card" style="max-width:560px">
  <p class="muted small"><?= lang('Budget.imp_choose') ?></p>
  <form method="post" action="<?= site_url('budgets/import') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="version_id" value="<?= (int) $ver['id'] ?>">
    <div class="field"><input type="file" name="file" accept=".xlsx,.xls,.csv" required></div>
    <button class="btn" type="submit"><?= lang('App.upload') ?></button>
  </form>
</div>

<?php if ($batches): ?>
  <div class="card">
    <h2><?= lang('App.history') ?></h2>
    <table class="grid tight">
      <thead><tr><th>#</th><th><?= lang('App.file') ?></th><th><?= lang('App.status') ?></th><th class="right"><?= lang('Budget.imp_cells') ?></th><th><?= lang('App.date') ?></th></tr></thead>
      <tbody>
        <?php foreach ($batches as $b): ?>
          <tr>
            <td class="muted"><?= (int) $b['id'] ?></td>
            <td><?= esc($b['filename']) ?></td>
            <td><span class="badge badge-<?= $b['status'] === 'committed' ? 'green' : 'gray' ?>"><?= esc($b['status']) ?></span></td>
            <td class="right mono"><?= (int) $b['journal_count'] ?></td>
            <td class="small muted"><?= esc((string) ($b['committed_at'] ?? $b['created_at'])) ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php endif ?>

<?= $this->endSection() ?>
