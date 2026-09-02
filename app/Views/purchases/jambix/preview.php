<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$s     = $parsed['summary'];
$rows  = $parsed['invoices'];
$shown = array_slice($rows, 0, 400);
?>

<div class="page-head">
  <div><h1>Import Jambix · Preview</h1><div class="muted small"><?= esc($batch['filename']) ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('purchases/jambix/' . $batch['id'] . '/map') ?>">Back to mapping</a></div>
</div>
<?= view('purchases/jambix/_steps', ['active' => 'preview', 'batch' => $batch]) ?>

<div class="card">
  <div class="row" style="gap:26px;flex-wrap:wrap">
    <div><div class="muted small">Source rows</div><div class="mono" style="font-size:1.2rem"><?= $s['lines_total'] + $s['skipped_rows'] ?></div></div>
    <div><div class="muted small">Invoices — new</div><div class="mono" style="font-size:1.2rem"><?= $s['invoices_new'] ?></div></div>
    <div><div class="muted small">→ post / draft</div><div class="mono" style="font-size:1.2rem"><?= $s['will_post'] ?> / <?= $s['will_draft'] ?></div></div>
    <div><div class="muted small">Already imported</div><div class="mono" style="font-size:1.2rem"><?= $s['invoices_exists'] ?></div></div>
    <div><div class="muted small">Errors</div><div class="mono" style="font-size:1.2rem;<?= $s['invoices_error'] ? 'color:var(--red)' : '' ?>"><?= $s['invoices_error'] ?></div></div>
    <div><div class="muted small">Lines (dup skipped)</div><div class="mono" style="font-size:1.2rem"><?= $s['lines_total'] - $s['lines_dup'] ?> <span class="muted">(<?= $s['lines_dup'] ?>)</span></div></div>
    <div><div class="muted small">Zero-cost lines</div><div class="mono" style="font-size:1.2rem"><?= $s['lines_zero'] ?></div></div>
    <div><div class="muted small">Budget total (<?= base_code() ?>)</div><div class="mono" style="font-size:1.2rem"><?= money($s['amount_total']) ?></div></div>
  </div>
  <div class="muted small" style="margin-top:10px">
    Will create <b><?= $s['suppliers_new'] ?></b> supplier(s), <b><?= $s['customers_new'] ?></b> customer(s),
    <b><?= $s['jobs_new'] ?></b> job(s). Cost account: <b><?= esc($acctLabel) ?></b>.
    <?php if ($s['skipped_rows']): ?> <?= $s['skipped_rows'] ?> row(s) skipped on status.<?php endif ?>
  </div>
</div>

<div class="card">
  <div style="overflow-x:auto">
    <table class="grid tight">
      <thead><tr>
        <th>External id</th><th>Supplier</th><th>Client</th><th>Dossier</th><th>Inv. date</th>
        <th class="right">Lines</th><th class="right">Budget</th><th>Result</th>
      </tr></thead>
      <tbody>
        <?php foreach ($shown as $r): ?>
          <tr<?= $r['action'] === 'error' ? ' style="background:var(--red-bg)"' : ($r['action'] === 'exists' ? ' class="muted"' : '') ?>>
            <td class="mono small nowrap"><?= esc($r['external_id']) ?></td>
            <td><?= esc($r['supplier']) ?><?= $r['supplier_new'] ? ' <span class="badge badge-gray" style="font-size:.7em">new</span>' : '' ?></td>
            <td class="small"><?= esc($r['client']) ?></td>
            <td class="mono small"><?= esc($r['doss_nr']) ?></td>
            <td class="small nowrap"><?= $r['invoice_date'] ? date_id($r['invoice_date']) : '—' ?></td>
            <td class="right mono small">
              <?= count($r['live_lines']) ?><?php if ($r['lines_dup']): ?> <span class="muted">+<?= $r['lines_dup'] ?> dup</span><?php endif ?>
              <?php if ($r['lines_zero']): ?> <span class="muted">· <?= $r['lines_zero'] ?> @0</span><?php endif ?>
            </td>
            <td class="right mono"><?= money($r['amount_total']) ?></td>
            <td class="small">
              <?php if ($r['action'] === 'error'): ?>
                <span class="badge badge-red"><?= esc(implode(' ', $r['errors'])) ?></span>
              <?php elseif ($r['action'] === 'exists'): ?>
                <span class="badge badge-gray">already imported</span>
              <?php elseif ($r['will_post']): ?>
                <span class="badge badge-green">post</span>
              <?php else: ?>
                <span class="badge badge-gray">draft (no cost)</span>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php if (count($rows) > count($shown)): ?>
    <p class="muted small">Showing first <?= count($shown) ?> of <?= count($rows) ?> invoices. All of them are committed.</p>
  <?php endif ?>
</div>

<div class="card">
  <form method="post" action="<?= site_url('purchases/jambix/' . $batch['id'] . '/commit') ?>"
    onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Importing…';return confirm('Create <?= $s['invoices_new'] ?> purchase invoices (<?= $s['will_post'] ?> posted)?');">
    <?= csrf_field() ?>
    <div class="btn-group">
      <button class="btn" type="submit">Commit import</button>
      <a class="btn ghost" href="<?= site_url('purchases/jambix') ?>">Cancel</a>
      <span class="muted small" style="align-self:center">This can take a minute for a large file.</span>
    </div>
  </form>
</div>

<?= $this->endSection() ?>
