<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$colOpt = static function ($selected) use ($headers) {
    $h = '<option value="">— not mapped —</option>';
    foreach ($headers as $i => $label) {
        $h .= '<option value="' . $i . '"' . ((string) $selected === (string) $i ? ' selected' : '') . '>' . esc($label) . '</option>';
    }

    return $h;
};
?>

<div class="page-head">
  <div><h1>Import payments · Map columns</h1><div class="muted small"><?= esc($batch['filename']) ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('purchases/payments/import') ?>">Cancel</a></div>
</div>
<?= view('purchases/payments/import/_steps', ['active' => 'map', 'batch' => $batch]) ?>

<form method="post" action="<?= site_url('purchases/payments/import/' . $batch['id'] . '/map') ?>">
  <?= csrf_field() ?>

  <div class="card">
    <h2>Batch payment settings</h2>
    <div class="row">
      <div class="field" style="max-width:160px">
        <label>Payment date *</label>
        <input type="date" name="payment_date" value="<?= esc($header['payment_date'] ?? date('Y-m-d'), 'attr') ?>" required>
      </div>
      <div class="field" style="max-width:280px">
        <label>Pay from account *</label>
        <select name="bank_account_id" required>
          <option value="">— bank / cash —</option>
          <?php foreach ($banks as $b): ?>
            <option value="<?= $b['id'] ?>" <?= (int) ($header['bank_account_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>>
              <?= esc($b['code'] . ' · ' . $b['name']) ?>
            </option>
          <?php endforeach ?>
        </select>
      </div>
      <div class="field"><label>Reference</label><input name="reference" value="<?= esc($header['reference'] ?? '', 'attr') ?>" placeholder="e.g. BCA run 05 Sep"></div>
    </div>
    <p class="muted small">One Supplier Payment is posted per supplier, dated as above, from this account. The account's currency must match the invoices.</p>
  </div>

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
      <?php foreach ($fields as $field => [$label, $required]): ?>
        <div class="field" style="max-width:240px">
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
      <button class="btn" type="submit">Save &amp; preview</button>
      <a class="btn ghost" href="<?= site_url('purchases/payments/import') ?>">Cancel</a>
    </div>
  </div>
</form>

<?= $this->endSection() ?>
