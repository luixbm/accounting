<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$map    = $opt['map'] ?? [];
$colOpt = static function ($selected) use ($headers) {
    $h = '<option value="">— not mapped —</option>';
    foreach ($headers as $i => $label) {
        $h .= '<option value="' . $i . '"' . ((string) $selected === (string) $i ? ' selected' : '') . '>' . esc($label) . '</option>';
    }

    return $h;
};
?>

<div class="page-head"><div><h1><?= ucfirst($kind) ?> Import · Map columns</h1><div class="muted small"><?= esc($batch['filename']) ?></div></div></div>
<?= view('imports/invoice/_steps', ['active' => 'map', 'batch' => $batch, 'base' => $base]) ?>

<form method="post" action="<?= site_url($base . '/' . $batch['id'] . '/map') ?>">
  <?= csrf_field() ?>
  <div class="card">
    <div class="row">
      <div class="field" style="max-width:260px">
        <label>Sheet</label>
        <select name="sheet">
          <?php foreach ($sheets as $s): ?>
            <option value="<?= esc($s, 'attr') ?>" <?= $batch['sheet'] === $s ? 'selected' : '' ?>><?= esc($s) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:140px"><label>Header row</label><input type="number" name="header_row" min="1" value="<?= (int) ($opt['headerRow'] ?? 1) ?>"></div>
      <div class="field" style="max-width:200px">
        <label>Date format</label>
        <select name="date_format">
          <?php foreach (['auto' => 'Auto-detect', 'dmy' => 'DD/MM/YYYY', 'mdy' => 'MM/DD/YYYY', 'ymd' => 'YYYY-MM-DD'] as $k => $l): ?>
            <option value="<?= $k ?>" <?= ($opt['dateFormat'] ?? 'auto') === $k ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach ?>
        </select>
      </div>
    </div>
  </div>

  <div class="card">
    <h2>Column mapping</h2>
    <div class="row">
      <?php foreach ($fields as $field => [$label, $required]): ?>
        <div class="field" style="min-width:230px">
          <label><?= esc($label) ?><?= $required ? ' *' : '' ?></label>
          <select name="map_<?= $field ?>"><?= $colOpt($map[$field] ?? '') ?></select>
        </div>
      <?php endforeach ?>
    </div>
    <p class="muted small">* required. PPN / PPh are per-invoice (taken from the first row of each group).</p>
  </div>

  <div class="card">
    <h2>First rows</h2>
    <div style="overflow-x:auto">
      <table class="grid tight mono">
        <thead><tr><th>#</th><?php foreach ($headers as $h): ?><th><?= esc($h) ?></th><?php endforeach ?></tr></thead>
        <tbody>
          <?php foreach ($sample as $r): ?>
            <tr><td class="muted"><?= $r['n'] ?></td>
              <?php foreach ($headers as $i => $_): ?><td><?= esc(mb_strimwidth((string) ($r['cells'][$i] ?? ''), 0, 22, '…')) ?></td><?php endforeach ?>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="btn-group">
      <button class="btn" type="submit">Save &amp; continue</button>
      <a class="btn ghost" href="<?= site_url($base) ?>">Cancel</a>
    </div>
  </div>
</form>

<?= $this->endSection() ?>
