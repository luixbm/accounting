<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
/** @var list<array<string,mixed>> $cfDefs  @var array<int,array<string,string>> $cfVals */
$opts = static fn (string $s): array => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $s))));

// Every column the table can show. Custom fields come through in full now -
// which ones are visible is a per-user choice made with the "Columns" menu.
$columns = [
    ['key' => 'code', 'label' => 'Code'],
    ['key' => 'name', 'label' => 'Name'],
];
foreach ($cfDefs as $d) {
    $columns[] = ['key' => 'cf_' . $d['field_key'], 'label' => $d['label'], 'cf' => $d];
}
foreach ([['email', 'Email'], ['phone', 'Phone'], ['npwp', 'NPWP'], ['status', 'Status']] as [$k, $lbl]) {
    $columns[] = ['key' => $k, 'label' => $lbl];
}

// Every column (base + custom) is visible by default; the "Columns" menu lets
// each user hide the ones they don't need, remembered in their browser.
$defaultHidden = [];
$span          = count($columns) + 1;
?>

<div class="page-head">
  <div>
    <h1><?= esc($label) ?>s</h1>
    <div class="muted small"><?= number_format($matched) ?><?= $matched !== $total ? ' of ' . number_format($total) : '' ?> record<?= $matched === 1 ? '' : 's' ?></div>
  </div>
  <div class="btn-group">
    <details class="colpick no-print" id="colpick">
      <summary class="btn ghost">Columns &#9662;</summary>
      <div class="colpick-panel">
        <div class="colpick-head">
          <button type="button" class="link-btn" data-all="1">All</button>
          <button type="button" class="link-btn" data-all="0">None</button>
        </div>
        <?php foreach ($columns as $c): ?>
          <label><input type="checkbox" value="<?= esc($c['key'], 'attr') ?>"> <?= esc($c['label']) ?></label>
        <?php endforeach ?>
      </div>
    </details>
    <a class="btn ghost" href="<?= site_url($route . '/export') ?>">Export</a>
    <?php if (user_can('masterdata.manage')): ?>
      <a class="btn ghost" href="<?= site_url($route . '/import') ?>">Import</a>
      <a class="btn" href="<?= site_url($route . '/new') ?>">+ New <?= esc($label) ?></a>
    <?php endif ?>
  </div>
</div>

<form class="filterbar no-print" method="get">
  <div class="field" style="min-width:220px">
    <label>Search</label>
    <input name="q" value="<?= esc($q ?? '') ?>" placeholder="code, name, email, NPWP">
  </div>
  <?php foreach ($cfDefs as $d): ?>
    <?php
    // Only offer a filter for pick-list fields (select / checkbox). Free-text
    // custom fields (bank name, account nr, …) are searchable via the Search box
    // and clutter the bar otherwise.
    if (! in_array($d['type'], ['select', 'checkbox'], true)) {
        continue;
    }
    $cur = $cf[$d['field_key']] ?? '';
    ?>
    <div class="field" style="max-width:220px">
      <label><?= esc($d['label']) ?></label>
      <?php if ($d['type'] === 'select'): ?>
        <select name="cf[<?= esc($d['field_key'], 'attr') ?>]">
          <option value="">All</option>
          <?php foreach ($opts((string) $d['options']) as $o): ?>
            <option value="<?= esc($o, 'attr') ?>" <?= (string) $cur === $o ? 'selected' : '' ?>><?= esc($o) ?></option>
          <?php endforeach ?>
        </select>
      <?php else: ?>
        <select name="cf[<?= esc($d['field_key'], 'attr') ?>]">
          <option value="">All</option>
          <option value="1" <?= $cur === '1' ? 'selected' : '' ?>>Yes</option>
          <option value="0" <?= $cur === '0' ? 'selected' : '' ?>>No</option>
        </select>
      <?php endif ?>
    </div>
  <?php endforeach ?>
  <button class="btn" type="submit">Filter</button>
  <?= view('partials/filter_clear') ?>
</form>

<div class="card">
  <div class="tbl-scroll">
    <table class="grid tight" id="partyTable">
      <thead>
        <tr>
          <?php foreach ($columns as $c): ?>
            <th data-col="<?= esc($c['key'], 'attr') ?>"><?= esc($c['label']) ?></th>
          <?php endforeach ?>
          <th class="no-print"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <?php foreach ($columns as $c): ?>
              <?php
              $k = $c['key'];
              if ($k === 'code') {
                  echo '<td data-col="code" class="mono nowrap">' . esc($r['code']) . '</td>';
              } elseif ($k === 'name') {
                  echo '<td data-col="name"><a href="' . site_url($route . '/' . $r['id']) . '">' . esc($r['name']) . '</a></td>';
              } elseif ($k === 'email') {
                  echo '<td data-col="email" class="small">' . esc($r['email']) . '</td>';
              } elseif ($k === 'phone') {
                  echo '<td data-col="phone" class="small">' . esc($r['phone']) . '</td>';
              } elseif ($k === 'npwp') {
                  echo '<td data-col="npwp" class="small mono">' . esc($r['npwp']) . '</td>';
              } elseif ($k === 'status') {
                  echo '<td data-col="status">' . ($r['is_active']
                      ? '<span class="badge badge-green">active</span>'
                      : '<span class="badge badge-gray">inactive</span>') . '</td>';
              } else { // custom field
                  $fk = $c['cf']['field_key'];
                  $rv = (string) ($cfVals[$r['id']][$fk] ?? '');
                  if (($c['cf']['type'] ?? '') === 'checkbox') {
                      $rv = $rv === '1' ? 'Yes' : ($rv === '0' ? 'No' : '');
                  }
                  echo '<td data-col="' . esc($k, 'attr') . '" class="small">' . esc($rv) . '</td>';
              }
              ?>
            <?php endforeach ?>
            <td class="right nowrap no-print">
              <a class="btn sm ghost" href="<?= site_url($route . '/' . $r['id']) ?>">Statement</a>
              <?php if (user_can('masterdata.manage')): ?>
                <a class="btn sm ghost" href="<?= site_url($route . '/' . $r['id'] . '/edit') ?>">Edit</a>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
        <?php if (! $rows): ?><tr><td colspan="<?= $span ?>" class="muted">No <?= esc(strtolower($label)) ?>s match these filters.</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
  <?= $pagerHtml ?>
</div>

<script>
(function () {
  var menu = document.getElementById('colpick');
  if (!menu) return;
  var KEY = 'partycols:' + <?= json_encode($route) ?>;
  var boxes = Array.prototype.slice.call(menu.querySelectorAll('input[type=checkbox]'));
  var defaultHidden = <?= json_encode($defaultHidden) ?>;

  function apply() {
    boxes.forEach(function (cb) {
      var vis = cb.checked;
      document.querySelectorAll('#partyTable [data-col="' + cb.value.replace(/"/g, '\\"') + '"]').forEach(function (el) {
        el.classList.toggle('col-hidden', !vis);
      });
    });
  }

  var hidden = defaultHidden;
  try {
    var saved = JSON.parse(localStorage.getItem(KEY) || 'null');
    if (Array.isArray(saved)) hidden = saved;
  } catch (e) {}

  boxes.forEach(function (cb) { cb.checked = hidden.indexOf(cb.value) === -1; });
  apply();

  function save() {
    var h = boxes.filter(function (cb) { return !cb.checked; }).map(function (cb) { return cb.value; });
    try { localStorage.setItem(KEY, JSON.stringify(h)); } catch (e) {}
  }

  menu.addEventListener('change', function () { apply(); save(); });
  menu.querySelectorAll('[data-all]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var on = btn.getAttribute('data-all') === '1';
      boxes.forEach(function (cb) { cb.checked = on; });
      apply(); save();
    });
  });
})();
</script>

<?= $this->endSection() ?>
