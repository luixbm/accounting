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

<div class="page-head">
  <div><h1>Import COA · Map columns</h1><div class="muted small"><?= esc($batch['filename']) ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('accounts/import') ?>">Cancel</a></div>
</div>
<?= view('accounts/import/_steps', ['active' => 'map', 'batch' => $batch]) ?>

<form method="post" action="<?= site_url('accounts/import/' . $batch['id'] . '/map') ?>">
  <?= csrf_field() ?>

  <div class="card">
    <div class="row">
      <div class="field" style="max-width:240px">
        <label>Sheet</label>
        <select name="sheet">
          <?php foreach ($sheets as $s): ?>
            <option value="<?= esc($s, 'attr') ?>" <?= $batch['sheet'] === $s ? 'selected' : '' ?>><?= esc($s) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:130px">
        <label>Header row</label>
        <input type="number" name="header_row" min="1" value="<?= (int) ($opt['headerRow'] ?? 1) ?>">
      </div>
    </div>
    <div class="row">
      <?php foreach ($fields as $field => [$label, $required]): ?>
        <div class="field" style="max-width:230px">
          <label><?= esc($label) ?><?= $required ? ' *' : '' ?></label>
          <select name="map_<?= $field ?>"><?= $colOpt($map[$field] ?? '') ?></select>
        </div>
      <?php endforeach ?>
    </div>
  </div>

  <?php if ($rawTypes): ?>
    <div class="card">
      <h2>Account types</h2>
      <p class="muted small">Map each type code in your file to one of the app's nine types. Tick <b>Cash/bank</b> for accounts that should appear as cash on the Bank screens.</p>
      <table class="grid tight">
        <thead><tr><th>In your file</th><th>App type</th><th>Cash/bank</th></tr></thead>
        <tbody>
          <?php foreach ($rawTypes as $i => $raw): ?>
            <?php [$guessType, $guessCash] = $typeMap[$raw] ?? ['asset', 0]; ?>
            <tr>
              <td class="mono"><?= esc($raw) ?><input type="hidden" name="type_raw[<?= $i ?>]" value="<?= esc($raw, 'attr') ?>"></td>
              <td>
                <select name="type_app[<?= $i ?>]">
                  <?php foreach ($appTypes as $k => $lbl): ?>
                    <option value="<?= $k ?>" <?= $guessType === $k ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                  <?php endforeach ?>
                </select>
              </td>
              <td><input type="checkbox" name="type_cash[<?= $i ?>]" value="1" <?= $guessCash ? 'checked' : '' ?>></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  <?php endif ?>

  <div class="card">
    <h2>First rows</h2>
    <div style="overflow-x:auto">
      <table class="grid tight">
        <thead><tr><th>#</th><?php foreach ($headers as $h): ?><th><?= esc($h) ?></th><?php endforeach ?></tr></thead>
        <tbody>
          <?php foreach ($sample as $r): ?>
            <tr><td class="muted"><?= $r['n'] ?></td>
              <?php foreach ($headers as $i => $_): ?><td><?= esc((string) ($r['cells'][$i] ?? '')) ?></td><?php endforeach ?>
            </tr>
          <?php endforeach ?>
          <?php if (! $sample): ?><tr><td colspan="<?= count($headers) + 1 ?>" class="muted">No data rows below the header.</td></tr><?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="btn-group">
      <button class="btn" type="submit"><?= $rawTypes ? 'Save &amp; preview' : 'Save &amp; continue' ?></button>
      <a class="btn ghost" href="<?= site_url('accounts/import') ?>">Cancel</a>
    </div>
  </div>
</form>

<?= $this->endSection() ?>
