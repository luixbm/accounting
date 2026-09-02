<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $t = $data['totals']; ?>

<div class="page-head"><h1><?= esc($title) ?></h1><div class="btn-group no-print"><a class="btn ghost" href="<?= site_url('reports') ?>">&lsaquo; All reports</a></div></div>
<?= view('reports/_period', ['f' => $f, 'showZeros' => true]) ?>

<div class="report-title">
  <div class="co"><?= esc(company_name()) ?></div>
  <h1><?= esc($title) ?></h1>
  <div class="muted"><?= date_id($from) ?> — <?= date_id($to) ?></div>
</div>

<div class="card">
  <table class="grid tight mono">
    <thead>
      <tr>
        <th rowspan="2">Code</th><th rowspan="2" style="font-family:sans-serif">Account</th>
        <th colspan="2" class="center">Opening</th>
        <th colspan="2" class="center">Movement</th>
        <th colspan="2" class="center">Ending</th>
      </tr>
      <tr>
        <th class="right">Debit</th><th class="right">Credit</th>
        <th class="right">Debit</th><th class="right">Credit</th>
        <th class="right">Debit</th><th class="right">Credit</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($data['blocks'] as $blk): ?>
        <?php $named = $blk['code'] !== ''; ?>
        <?php if ($named): ?>
          <tr class="hdr-row"><td colspan="8" style="font-family:sans-serif"><span class="mono small"><?= esc($blk['code']) ?></span> <?= esc($blk['name']) ?></td></tr>
        <?php endif ?>
        <?php foreach ($blk['rows'] as $r): ?>
          <tr>
            <td<?= $named ? ' style="padding-left:22px"' : '' ?>><?= esc($r['code']) ?></td>
            <td style="font-family:sans-serif"><?= esc($r['name']) ?></td>
            <td class="right"><?= money($r['open_d'], 2, true) ?></td>
            <td class="right"><?= money($r['open_c'], 2, true) ?></td>
            <td class="right"><?= money($r['mv_d'], 2, true) ?></td>
            <td class="right"><?= money($r['mv_c'], 2, true) ?></td>
            <td class="right"><?= money($r['end_d'], 2, true) ?></td>
            <td class="right"><?= money($r['end_c'], 2, true) ?></td>
          </tr>
        <?php endforeach ?>
        <?php if ($named): $s = $blk['subtotal']; ?>
          <tr class="sub-row">
            <td colspan="2" class="right" style="font-family:sans-serif">Subtotal — <?= esc($blk['name']) ?></td>
            <td class="right"><?= money($s['open_d'], 2, true) ?></td>
            <td class="right"><?= money($s['open_c'], 2, true) ?></td>
            <td class="right"><?= money($s['mv_d'], 2, true) ?></td>
            <td class="right"><?= money($s['mv_c'], 2, true) ?></td>
            <td class="right"><?= money($s['end_d'], 2, true) ?></td>
            <td class="right"><?= money($s['end_c'], 2, true) ?></td>
          </tr>
        <?php endif ?>
      <?php endforeach ?>
      <?php if (! $data['rows']): ?><tr><td colspan="8" class="muted" style="font-family:sans-serif">No activity in this range.</td></tr><?php endif ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="2">TOTAL</td>
        <td class="right"><?= money($t['open_d']) ?></td>
        <td class="right"><?= money($t['open_c']) ?></td>
        <td class="right"><?= money($t['mv_d']) ?></td>
        <td class="right"><?= money($t['mv_c']) ?></td>
        <td class="right"><?= money($t['end_d']) ?></td>
        <td class="right"><?= money($t['end_c']) ?></td>
      </tr>
    </tfoot>
  </table>
  <?php $obal = abs($t['end_d'] - $t['end_c']) < 0.5; ?>
  <p class="small <?= $obal ? 'muted' : '' ?>" style="<?= $obal ? '' : 'color:var(--red);font-weight:700' ?>">
    <?= $obal ? 'Ending debits and credits agree.' : 'WARNING: ending debits and credits do not agree — investigate unbalanced journals.' ?>
  </p>
</div>

<?= $this->endSection() ?>
