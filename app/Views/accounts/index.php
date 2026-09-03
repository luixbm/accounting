<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $f = $filters ?? ['q' => '', 'type' => '', 'group' => 0, 'status' => '']; ?>

<div class="page-head">
  <div>
    <h1><?= lang('Nav.chart_of_accounts') ?></h1>
    <div class="muted small"><?= lang('Setup.n_shown', [count($accounts)]) ?><?= isset($totalCount) && $totalCount !== count($accounts) ? ' ' . lang('Setup.of_total', [$totalCount]) : '' ?></div>
  </div>
  <?php if (user_can('masterdata.manage')): ?>
    <div class="btn-group">
      <a class="btn ghost" href="<?= site_url('accounts/import') ?>"><?= lang('Txn.import_spreadsheet') ?></a>
      <a class="btn" href="<?= site_url('accounts/new') ?>"><?= lang('Setup.new_account') ?></a>
    </div>
  <?php endif ?>
</div>

<?php if (! $accounts && $otherCompanies && user_can('masterdata.manage')): ?>
  <div class="card" style="max-width:520px">
    <h2><?= lang('Setup.start_coa_h') ?></h2>
    <p class="muted small"><?= lang('Setup.start_coa_note') ?></p>
    <form method="post" action="<?= site_url('accounts/copy-from') ?>" class="inline">
      <?= csrf_field() ?>
      <select name="source_company_id" style="max-width:260px">
        <?php foreach ($otherCompanies as $c): ?>
          <option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option>
        <?php endforeach ?>
      </select>
      <button class="btn" type="submit"><?= lang('Setup.copy_coa') ?></button>
    </form>
  </div>
<?php endif ?>

<form class="filterbar no-print" method="get">
  <div class="field" style="min-width:220px">
    <label><?= lang('App.search') ?></label>
    <input name="q" value="<?= esc($f['q']) ?>" placeholder="<?= esc(lang('Txn.code_name_ph'), 'attr') ?>">
  </div>
  <div class="field" style="max-width:190px">
    <label><?= lang('Report.col_type') ?></label>
    <select name="type">
      <option value=""><?= lang('Setup.all_types') ?></option>
      <?php foreach ($types as $k => $lbl): ?>
        <option value="<?= $k ?>" <?= $f['type'] === $k ? 'selected' : '' ?>><?= esc($lbl) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="field" style="max-width:280px">
    <label><?= lang('Setup.group') ?></label>
    <select name="group">
      <option value=""><?= lang('Setup.all_groups') ?></option>
      <?php foreach ($groupList as $g): ?>
        <option value="<?= $g['id'] ?>" <?= (int) $f['group'] === (int) $g['id'] ? 'selected' : '' ?>>
          <?= esc($g['code'] . ' · ' . $g['name']) ?>
        </option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="field" style="max-width:150px">
    <label><?= lang('App.status') ?></label>
    <select name="status">
      <option value=""><?= lang('App.all') ?></option>
      <option value="active" <?= $f['status'] === 'active' ? 'selected' : '' ?>><?= lang('App.active') ?></option>
      <option value="inactive" <?= $f['status'] === 'inactive' ? 'selected' : '' ?>><?= lang('App.inactive') ?></option>
    </select>
  </div>
  <button class="btn" type="submit"><?= lang('App.filter') ?></button>
  <?= view('partials/filter_clear') ?>
</form>

<div class="card">
  <table class="grid tight">
    <thead>
      <tr>
        <th><?= lang('Report.col_code') ?></th><th><?= lang('Report.col_name') ?></th><th><?= lang('Setup.col_parent') ?></th><th><?= lang('Report.col_type') ?></th><th><?= lang('Setup.col_normal') ?></th><th><?= lang('Setup.col_subledger') ?></th>
        <th><?= lang('Setup.col_flags') ?></th><th><?= lang('App.status') ?></th><th></th>
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
            <?= $a['is_group'] ? lang('Setup.flag_header') . ' ' : '' ?><?= $a['is_cash'] ? lang('Setup.flag_cash') . ' ' : '' ?>
          </td>
          <td><?= $a['is_active'] ? '<span class="badge badge-green">' . esc(lang('App.active')) . '</span>' : '<span class="badge badge-gray">' . esc(lang('App.inactive')) . '</span>' ?></td>
          <td class="right nowrap">
            <?php if (user_can('masterdata.manage')): ?>
              <a class="btn sm ghost" href="<?= site_url('accounts/' . $a['id'] . '/edit') ?>"><?= lang('App.edit') ?></a>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (! $accounts): ?>
        <tr><td colspan="9" class="muted"><?= lang('Setup.no_accounts_match') ?></td></tr>
      <?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
