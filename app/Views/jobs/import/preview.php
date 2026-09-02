<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$s     = $parsed['summary'];
$rows  = $parsed['jobs'];
$shown = array_slice($rows, 0, 500);
?>

<div class="page-head">
  <div><h1>Import Jambix jobs · Preview</h1><div class="muted small"><?= esc($batch['filename']) ?></div></div>
  <div class="btn-group"><a class="btn ghost" href="<?= site_url('jobs/import/' . $batch['id'] . '/map') ?>">Back to mapping</a></div>
</div>
<?= view('jobs/import/_steps', ['active' => 'preview', 'batch' => $batch]) ?>

<div class="card">
  <div class="row" style="gap:26px;flex-wrap:wrap">
    <div><div class="muted small">Source rows</div><div class="mono" style="font-size:1.2rem"><?= $s['rows'] ?></div></div>
    <div><div class="muted small">Jobs — new</div><div class="mono" style="font-size:1.2rem"><?= $s['jobs_new'] ?></div></div>
    <div><div class="muted small">Jobs — update</div><div class="mono" style="font-size:1.2rem"><?= $s['jobs_update'] ?></div></div>
    <div><div class="muted small">Errors</div><div class="mono" style="font-size:1.2rem;<?= $s['jobs_error'] ? 'color:var(--red)' : '' ?>"><?= $s['jobs_error'] ?></div></div>
    <div><div class="muted small">New customers</div><div class="mono" style="font-size:1.2rem"><?= $s['cust_new'] ?></div></div>
    <div><div class="muted small">Pax total</div><div class="mono" style="font-size:1.2rem"><?= number_format((int) $s['pax_total']) ?></div></div>
    <div><div class="muted small">Sales ref (<?= base_code() ?>)</div><div class="mono" style="font-size:1.2rem"><?= money($s['sales_total']) ?></div></div>
    <div><div class="muted small">Buy ref (<?= base_code() ?>)</div><div class="mono" style="font-size:1.2rem"><?= money($s['buy_total']) ?></div></div>
  </div>
  <div class="muted small" style="margin-top:10px">
    Sales / Buy are stored as Jambix reference figures on each job — the P&amp;L reports keep using the posted transactions.
    <?php if ($s['blank']): ?> <?= $s['blank'] ?> blank row(s) ignored.<?php endif ?>
  </div>
</div>

<div class="card">
  <div style="overflow-x:auto">
    <table class="grid tight">
      <thead><tr>
        <th>Dossier</th><th>Name</th><th>Client</th><th>Arrival</th><th>End</th>
        <th class="right">Pax</th><th class="right">Sales</th><th class="right">Buy</th>
        <th class="right">Net</th><th class="right">Margin</th><th>Cat.</th><th>Status</th><th>Result</th>
      </tr></thead>
      <tbody>
        <?php foreach ($shown as $r): ?>
          <?php
          $net    = (float) $r['sales_ref'] - (float) $r['buy_ref'];
          $margin = (float) $r['sales_ref'] != 0.0 ? $net / (float) $r['sales_ref'] * 100 : null;
          ?>
          <tr<?= $r['action'] === 'error' ? ' style="background:var(--red-bg)"' : '' ?>>
            <td class="mono small nowrap"><?= esc($r['code']) ?></td>
            <td class="small"><?= esc($r['name']) ?></td>
            <td class="small"><?= esc($r['client']) ?><?= $r['client_new'] ? ' <span class="badge badge-gray" style="font-size:.7em">new</span>' : '' ?></td>
            <td class="small nowrap"><?= $r['start_date'] ? date_id($r['start_date']) : '—' ?></td>
            <td class="small nowrap"><?= $r['end_date'] ? date_id($r['end_date']) : '—' ?></td>
            <td class="right mono small"><?= $r['pax'] === null ? '' : (int) $r['pax'] ?></td>
            <td class="right mono small"><?= money((float) $r['sales_ref'], 0, true) ?></td>
            <td class="right mono small"><?= money((float) $r['buy_ref'], 0, true) ?></td>
            <td class="right mono small" style="<?= $net < 0 ? 'color:var(--red)' : '' ?>"><?= money($net, 0, true) ?></td>
            <td class="right mono small"><?= $margin === null ? '' : number_format($margin, 1) . '%' ?></td>
            <td class="small"><?= esc($r['category']) ?></td>
            <td class="small"><?= esc($r['jambix_status']) ?><?= $r['status'] === 'closed' ? ' <span class="badge badge-gray" style="font-size:.7em">closed</span>' : '' ?></td>
            <td class="small">
              <?php if ($r['action'] === 'error'): ?>
                <span class="badge badge-red"><?= esc(implode(' ', $r['errors'])) ?></span>
              <?php elseif ($r['action'] === 'update'): ?>
                <span class="badge badge-gray">update</span>
              <?php else: ?>
                <span class="badge badge-green">new</span>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
        <?php if (! $shown): ?><tr><td colspan="13" class="muted">Nothing to import.</td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
  <?php if (count($rows) > count($shown)): ?>
    <p class="muted small">Showing first <?= count($shown) ?> of <?= count($rows) ?> rows. All of them are committed.</p>
  <?php endif ?>
</div>

<div class="card">
  <form method="post" action="<?= site_url('jobs/import/' . $batch['id'] . '/commit') ?>"
    onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Importing…';return confirm('Create <?= $s['jobs_new'] ?> job(s) and update <?= $s['jobs_update'] ?>?');">
    <?= csrf_field() ?>
    <div class="btn-group">
      <button class="btn" type="submit">Commit import</button>
      <a class="btn ghost" href="<?= site_url('jobs/import') ?>">Cancel</a>
      <span class="muted small" style="align-self:center">This can take a minute for a large file.</span>
    </div>
  </form>
</div>

<?= $this->endSection() ?>
