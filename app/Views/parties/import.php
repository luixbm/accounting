<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div>
    <h1>Import <?= esc($label) ?>s</h1>
    <div class="muted small">Upserts by <strong>Code</strong> — matching codes are updated, new codes are added.</div>
  </div>
  <div class="btn-group">
    <a class="btn ghost" href="<?= site_url($route) ?>">Back to <?= esc($label) ?>s</a>
  </div>
</div>

<?php if (empty($preview)): ?>

  <div class="card" style="max-width:620px">
    <p class="small">
      Upload an <code>.xlsx</code>, <code>.xls</code> or <code>.csv</code> file. The first row must be a header
      with at least <strong>Code</strong> and <strong>Name</strong> columns; other recognised headers:
      Email, Phone, <?= $label === 'Customer' ? 'Client group, Country, ' : '' ?>Tax ID / NPWP, Address, Active,
      plus any custom-field labels.
    </p>
    <p class="small muted">
      Easiest path: <a href="<?= site_url($route . '/export') ?>">export the current list</a>, edit it, and upload it back.
    </p>
    <form method="post" action="<?= site_url($route . '/import') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="field" style="max-width:360px">
        <label>File</label>
        <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
      </div>
      <button class="btn" type="submit">Preview</button>
    </form>
  </div>

<?php else: ?>
  <?php
  $c = $preview['counts'];
  $items = $preview['items'];
  $applyable = $c['create'] + $c['update'];
  ?>

  <div class="card" style="max-width:760px">
    <p>
      <span class="badge badge-green"><?= $c['create'] ?> to create</span>
      <span class="badge badge-gray"><?= $c['update'] ?> to update</span>
      <?php if ($c['error']): ?><span class="badge badge-red"><?= $c['error'] ?> skipped (errors)</span><?php endif ?>
    </p>

    <div style="overflow-x:auto">
      <table class="grid tight">
        <thead>
          <tr><th>Row</th><th>Action</th><th>Code</th><th>Name</th><th>Note</th></tr>
        </thead>
        <tbody>
          <?php foreach (array_slice($items, 0, 300) as $it): ?>
            <?php
            $b = ['create' => 'badge-green', 'update' => 'badge-gray', 'error' => 'badge-red'][$it['action']] ?? 'badge-gray';
            ?>
            <tr>
              <td class="mono"><?= (int) $it['n'] ?></td>
              <td><span class="badge <?= $b ?>"><?= esc($it['action']) ?></span></td>
              <td class="mono"><?= esc($it['data']['code'] ?? '') ?></td>
              <td><?= esc($it['data']['name'] ?? '') ?></td>
              <td class="small muted"><?= esc($it['msg'] ?? '') ?></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php if (count($items) > 300): ?><p class="small muted">Showing the first 300 of <?= count($items) ?> rows.</p><?php endif ?>

    <form method="post" action="<?= site_url($route . '/import/commit') ?>" style="margin-top:12px">
      <?= csrf_field() ?>
      <input type="hidden" name="payload" value="<?= esc($payload, 'attr') ?>">
      <button class="btn" type="submit" <?= $applyable ? '' : 'disabled' ?>>
        Import <?= $applyable ?> row<?= $applyable === 1 ? '' : 's' ?>
      </button>
      <a class="btn ghost" href="<?= site_url($route . '/import') ?>">Cancel</a>
    </form>
  </div>

<?php endif ?>

<?= $this->endSection() ?>
