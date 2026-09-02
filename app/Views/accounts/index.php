<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $f = $filters ?? ['q' => '', 'type' => '', 'group' => 0, 'status' => '']; ?>

<div class="page-head">
  <div>
    <h1>Chart of Accounts</h1>
    <div class="muted small"><?= count($accounts) ?> shown<?= isset($totalCount) && $totalCount !== count($accounts) ? ' of ' . $totalCount : '' ?></div>
  </div>
  <?php if (user_can('masterdata.manage')): ?>
    <div class="btn-group">
      <a class="btn ghost" href="<?= site_url('accounts/import') ?>">Import from spreadsheet</a>
      <a class="btn" href="<?= site_url('accounts/new') ?>">+ New Account</a>
    </div>
  <?php endif ?>
</div>

<?php if (! $accounts && $otherCompanies && user_can('masterdata.manage')): ?>
  <div class="card" style="max-width:520px">
    <h2>Start this company's chart of accounts</h2>
    <p class="muted small">Copy an existing chart from another company, then adjust it. Or add accounts one by one.</p>
    <form method="post" action="<?= site_url('accounts/copy-from') ?>" class="inline">
      <?= csrf_field() ?>
      <select name="source_company_id" style="max-width:260px">
        <?php foreach ($otherCompanies as $c): ?>
          <option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option>
        <?php endforeach ?>
      </select>
      <button class="btn" type="submit">Copy chart of accounts</button>
    </form>
  </div>
<?php endif ?>

<form class="filterbar no-print" method="get">
  <div class="field" style="min-width:220px">
    <label>Search</label>
    <input name="q" value="<?= esc($f['q']) ?>" placeholder="code or name">
  </div>
  <div class="field" style="max-width:190px">
    <label>Type</label>
    <select name="type">
      <option value="">All types</option>
      <?php foreach ($types as $k => $lbl): ?>
        <option value="<?= $k ?>" <?= $f['type'] === $k ? 'selected' : '' ?>><?= esc($lbl) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="field" style="max-width:280px">
    <label>Group</label>
    <select name="group">
      <option value="">All groups</option>
      <?php foreach ($groupList as $g): ?>
        <option value="<?= $g['id'] ?>" <?= (int) $f['group'] === (int) $g['id'] ? 'selected' : '' ?>>
          <?= esc($g['code'] . ' · ' . $g['name']) ?>
        </option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="field" style="max-width:150px">
    <label>Status</label>
    <select name="status">
      <option value="">All</option>
      <option value="active" <?= $f['status'] === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="inactive" <?= $f['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
    </select>
  </div>
  <button class="btn" type="submit">Filter</button>
  <a class="btn ghost" href="<?= site_url('accounts') ?>">Reset</a>
</form>

<div class="card">
  <table class="grid tight">
    <thead>
      <tr>
        <th>Code</th><th>Name</th><th>Parent</th><th>Type</th><th>Normal</th><th>Subledger</th>
        <th>Flags</th><th>Status</th><th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($accounts as $a): ?>
        <tr<?= $a['is_group'] ? ' class="grp-row"' : '' ?>>
          <td class="mono nowrap"><?= esc($a['code']) ?></td>
          <td<?= $a['parent_id'] && ! $a['is_group'] ? ' style="padding-left:22px"' : '' ?>><?= esc($a['name']) ?></td>
          <td class="small muted mono nowrap"><?= $a['parent_code'] ?? '' ? esc($a['parent_code'] . ' · ' . $a['parent_name']) : '<span class="muted">—</span>' ?></td>
          <td class="small"><?= esc($types[$a['type']] ?? $a['type']) ?></td>
          <td class="center"><?= esc($a['normal_balance']) ?></td>
          <td class="small"><?= $a['subledger'] !== 'none' ? esc(ucfirst($a['subledger'])) : '' ?></td>
          <td class="small muted">
            <?= $a['is_group'] ? 'header ' : '' ?><?= $a['is_cash'] ? 'cash ' : '' ?>
          </td>
          <td><?= $a['is_active'] ? '<span class="badge badge-green">active</span>' : '<span class="badge badge-gray">inactive</span>' ?></td>
          <td class="right nowrap">
            <?php if (user_can('masterdata.manage')): ?>
              <a class="btn sm ghost" href="<?= site_url('accounts/' . $a['id'] . '/edit') ?>">Edit</a>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (! $accounts): ?>
        <tr><td colspan="9" class="muted">No accounts match these filters.</td></tr>
      <?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
