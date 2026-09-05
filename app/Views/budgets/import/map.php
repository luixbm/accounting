<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$map    = $opt['map'] ?? [];
$colOpt = static function ($selected) use ($headers) {
    $h = '<option value="">—</option>';
    foreach ($headers as $i => $label) {
        $h .= '<option value="' . $i . '"' . ((string) $selected === (string) $i ? ' selected' : '') . '>' . esc($label) . '</option>';
    }

    return $h;
};
?>

<div class="page-head">
  <div><h1><?= lang('Budget.imp_map') ?></h1><div class="muted small"><?= esc($batch['filename']) ?><?= $ver ? ' → ' . esc($ver['name']) : '' ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('budgets/import?version=' . ($ver['id'] ?? '')) ?>"><?= lang('App.cancel') ?></a></div>
</div>

<form method="post" action="<?= site_url('budgets/import/' . $batch['id'] . '/map') ?>">
  <?= csrf_field() ?>
  <div class="card">
    <div class="row">
      <div class="field" style="max-width:240px">
        <label><?= lang('Budget.imp_sheet') ?></label>
        <select name="sheet">
          <?php foreach ($sheets as $s): ?>
            <option value="<?= esc($s, 'attr') ?>" <?= $batch['sheet'] === $s ? 'selected' : '' ?>><?= esc($s) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:130px">
        <label><?= lang('Budget.imp_header_row') ?></label>
        <input type="number" name="header_row" min="1" value="<?= (int) ($opt['headerRow'] ?? 1) ?>">
      </div>
    </div>
    <div class="row">
      <?php foreach ($fields as $field => [$label, $required]): ?>
        <div class="field" style="max-width:190px">
          <label><?= esc($label) ?><?= $required ? ' *' : '' ?></label>
          <select name="map_<?= $field ?>"><?= $colOpt($map[$field] ?? '') ?></select>
        </div>
      <?php endforeach ?>
    </div>
  </div>

  <div class="card">
    <h2><?= lang('Import.first_rows') ?></h2>
    <div style="overflow-x:auto">
      <table class="grid tight">
        <thead><tr><th>#</th><?php foreach ($headers as $h): ?><th><?= esc($h) ?></th><?php endforeach ?></tr></thead>
        <tbody>
          <?php foreach ($sample as $r): ?>
            <tr><td class="muted"><?= $r['n'] ?></td>
              <?php foreach ($headers as $i => $_): ?><td><?= esc((string) ($r['cells'][$i] ?? '')) ?></td><?php endforeach ?>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="btn-group">
      <button class="btn" type="submit"><?= lang('Budget.imp_preview') ?></button>
      <a class="btn ghost" href="<?= site_url('budgets/import?version=' . ($ver['id'] ?? '')) ?>"><?= lang('App.cancel') ?></a>
    </div>
  </div>
</form>

<?= $this->endSection() ?>
