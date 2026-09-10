<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php

$m = $summary['meta'];

$pctText = static fn (?float $f): string => $f === null ? '' : number_format($f * 100, 1) . '%';

$deltaCell = static function (?float $f): string {
    if ($f === null) {
        return '<span class="muted">—</span>';
    }
    $up  = $f >= 0;
    $col = $up ? 'var(--green)' : 'var(--red)';

    return '<span style="color:' . $col . ';font-weight:600">' . ($up ? '▲' : '▼') . ' '
        . number_format(abs($f) * 100, 1) . '%</span>';
};
?>

<div class="page-head">
  <h1><?= esc($title) ?></h1>
  <div class="btn-group no-print"><a class="btn ghost" href="<?= site_url('reports') ?>">&lsaquo; <?= lang('Report.v_all_reports') ?></a></div>
</div>

<?= view('reports/_period', ['f' => $f, 'showCompare' => false, 'showZeros' => false]) ?>

<div class="report-title">
  <div class="co"><?= esc(company_legal_name()) ?></div>
  <h1><?= esc($title) ?></h1>
  <div class="muted"><?= esc($f['label']) ?> &nbsp;·&nbsp; <?= date_id($m['from']) ?> — <?= date_id($m['to']) ?></div>
</div>

<div class="row" style="align-items:flex-start;gap:22px">
  <div class="card" style="flex:1.2;min-width:340px">
    <h2><?= lang('Dashboard.summary_pl') ?></h2>
    <table class="grid tight">
      <thead>
        <tr>
          <th><?= lang('Report.es_line') ?></th>
          <th class="right"><?= date_id($m['from']) ?><br><span class="muted small"><?= date_id($m['to']) ?></span></th>
          <th class="right muted"><?= date_id($m['prevFrom']) ?><br><span class="small"><?= date_id($m['prevTo']) ?></span></th>
          <th class="right"><?= lang('Report.es_pct_sales') ?></th>
          <th class="right"><?= lang('Report.es_yoy') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($summary['pnl'] as $r): ?>
          <?php
          $isTotal = $r['level'] === 'total';
          $isChild = $r['level'] === 'child';
          $showPct = ! $isChild && $r['key'] !== 'sales' && $r['pctCur'] !== null;
          ?>
          <tr class="<?= $isTotal ? 'subtotal' : '' ?>">
            <td<?= $isChild ? ' style="padding-left:24px"' : '' ?><?= $isChild ? ' class="muted small"' : '' ?>><?= esc($r['label']) ?></td>
            <td class="right mono <?= $isChild ? 'muted small' : '' ?>"><?= money($r['cur']) ?></td>
            <td class="right mono muted <?= $isChild ? 'small' : '' ?>"><?= money($r['prev']) ?></td>
            <td class="right mono <?= $isChild ? 'muted small' : '' ?>"><?= $showPct ? $pctText($r['pctCur']) : '' ?></td>
            <td class="right small"><?= $isChild ? '' : $deltaCell($r['yoy']) ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>

  <div class="card" style="flex:1;min-width:300px">
    <h2><?= lang('Dashboard.summary_bs') ?></h2>
    <div class="muted small" style="margin-bottom:6px"><?= lang('Report.es_position_as_of', [date_id($m['asOf'])]) ?></div>
    <table class="grid tight">
      <thead>
        <tr><th><?= lang('Report.es_line') ?></th><th class="right"><?= date_id($m['asOf']) ?></th><th class="right muted"><?= date_id($m['prevAsOf']) ?></th></tr>
      </thead>
      <tbody>
        <?php foreach ($summary['bs'] as $r): ?>
          <?php if ($r['level'] === 'head'): ?>
            <tr class="grp-row"><td colspan="3"><?= esc($r['label']) ?></td></tr>
          <?php else: ?>
            <?php $isTotal = $r['level'] === 'total';
            $isChild = $r['level'] === 'child'; ?>
            <tr class="<?= $isTotal ? 'subtotal' : '' ?>">
              <td<?= $isChild ? ' style="padding-left:18px"' : '' ?>><?= esc($r['label']) ?></td>
              <td class="right mono"><?= money($r['cur']) ?></td>
              <td class="right mono muted"><?= money($r['prev']) ?></td>
            </tr>
          <?php endif ?>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
