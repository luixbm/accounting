<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$map    = $opt['map'] ?? [];
$colOpt = static function ($selected) use ($headers) {
    $h = '<option value="">— not mapped —</option>';
    foreach ($headers as $i => $label) {
        $sel = (string) $selected === (string) $i ? ' selected' : '';
        $h .= '<option value="' . $i . '"' . $sel . '>' . esc($label) . '</option>';
    }

    return $h;
};
$mode = $opt['amountMode'] ?? 'credit_in';
?>

<div class="page-head">
  <div><h1>Statement · Map columns</h1><div class="muted small"><?= esc($st['filename']) ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('banking/reconcile') ?>">Cancel</a></div>
</div>

<form method="post" action="<?= site_url('banking/reconcile/' . $st['id'] . '/map') ?>">
  <?= csrf_field() ?>

  <div class="card">
    <div class="row">
      <div class="field" style="max-width:240px">
        <label>Sheet</label>
        <select name="sheet">
          <?php foreach ($sheets as $s): ?>
            <option value="<?= esc($s, 'attr') ?>" <?= $st['sheet'] === $s ? 'selected' : '' ?>><?= esc($s) ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:120px">
        <label>Header row</label>
        <input type="number" name="header_row" min="1" value="<?= (int) ($opt['headerRow'] ?? 1) ?>">
      </div>
      <div class="field" style="max-width:170px">
        <label>Date format</label>
        <select name="date_format">
          <?php foreach (['auto' => 'Auto', 'dmy' => 'D/M/Y', 'mdy' => 'M/D/Y', 'ymd' => 'Y/M/D'] as $k => $v): ?>
            <option value="<?= $k ?>" <?= ($opt['dateFormat'] ?? 'auto') === $k ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field" style="max-width:240px">
        <label>Amount convention</label>
        <select name="amount_mode">
          <option value="credit_in" <?= $mode === 'credit_in' ? 'selected' : '' ?>>Credit / positive = money IN</option>
          <option value="debit_in" <?= $mode === 'debit_in' ? 'selected' : '' ?>>Debit / positive = money IN</option>
        </select>
      </div>
    </div>
    <p class="muted small">Map either one signed <b>Amount</b> column, or separate <b>Debit</b> and <b>Credit</b> columns.</p>
    <div class="row">
      <?php foreach ($fields as $field => [$label, $required]): ?>
        <div class="field" style="max-width:230px">
          <label><?= esc($label) ?><?= $required ? ' *' : '' ?></label>
          <select name="map_<?= $field ?>"><?= $colOpt($map[$field] ?? '') ?></select>
        </div>
      <?php endforeach ?>
    </div>
  </div>

  <div class="card">
    <h2>First rows</h2>
    <div style="overflow-x:auto">
      <table class="grid tight">
        <thead><tr><th>#</th><?php foreach ($headers as $h): ?><th><?= esc($h) ?></th><?php endforeach ?></tr></thead>
        <tbody>
          <?php foreach ($sample as $r): ?>
            <tr>
              <td class="muted"><?= $r['n'] ?></td>
              <?php foreach ($headers as $i => $_): ?><td><?= esc((string) ($r['cells'][$i] ?? '')) ?></td><?php endforeach ?>
            </tr>
          <?php endforeach ?>
          <?php if (! $sample): ?><tr><td colspan="<?= count($headers) + 1 ?>" class="muted">No data rows found below the header.</td></tr><?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="btn-group">
      <button class="btn" type="submit">Import &amp; auto-match</button>
      <a class="btn ghost" href="<?= site_url('banking/reconcile') ?>">Cancel</a>
    </div>
  </div>
</form>

<?= $this->endSection() ?>
