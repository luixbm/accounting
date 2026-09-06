<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php

use App\Libraries\Chart\Svg;

$pctText = static fn (float $f): string => number_format($f * 100, 1) . '%';

// YoY / change cell: ▲ green when up, ▼ red when down, — when no base.
$deltaCell = static function (?float $f): string {
    if ($f === null) {
        return '<span class="muted">—</span>';
    }
    $up  = $f >= 0;
    $col = $up ? 'var(--green)' : 'var(--red)';

    return '<span style="color:' . $col . ';font-weight:600">' . ($up ? '▲' : '▼') . ' '
        . number_format(abs($f) * 100, 1) . '%</span>';
};
$growth = static fn (float $cur, float $base): ?float => abs($base) > 0.005 ? ($cur - $base) / abs($base) : null;

$mName    = $month > 0 ? date('F', mktime(0, 0, 0, $month, 1)) : '';
$sumMeta  = $summary['meta'];
?>

<div class="page-head">
  <div>
    <h1><?= lang('Dashboard.title') ?></h1>
    <form method="get" class="inline small no-print" style="margin-top:6px;gap:8px;flex-wrap:wrap">
      <span class="muted"><?= lang('App.year') ?></span>
      <select name="year" onchange="this.form.submit()" style="width:auto;padding:4px 7px">
        <?php foreach ($years as $y): ?>
          <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
        <?php endforeach ?>
      </select>
      <span class="muted"><?= lang('App.month') ?></span>
      <select name="month" onchange="this.form.submit()" style="width:auto;padding:4px 7px">
        <option value=""><?= lang('Dashboard.all_year') ?></option>
        <?php for ($m = 1; $m <= 12; $m++): ?>
          <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
        <?php endfor ?>
      </select>
      <span class="muted">
        <?php if ($month > 0): ?>
          <?= lang('Dashboard.mtd_ytd_through', [$mName, date_id($winTo)]) ?>
        <?php else: ?>
          <?= lang('Dashboard.ytd_through', [date_id($winTo)]) ?>
        <?php endif ?>
      </span>
    </form>
  </div>
  <div class="btn-group no-print">
    <a class="btn" href="<?= site_url('journals/new') ?>"><?= lang('Dashboard.new_journal') ?></a>
  </div>
</div>

<?php $anns = current_announcements(); ?>
<?php if ($anns): ?>
  <div class="card announce-card">
    <div class="announce-card-head">
      <h3><?= lang('Announce.card_title') ?></h3>
      <?php if (user_can('settings.manage')): ?>
        <a class="btn sm ghost no-print" href="<?= site_url('announcements') ?>"><?= lang('Announce.manage') ?></a>
      <?php endif ?>
    </div>
    <ul class="announce-list">
      <?php foreach ($anns as $a): ?>
        <?php $lvl = in_array($a['level'], ['info', 'warning', 'success'], true) ? $a['level'] : 'info'; ?>
        <li class="announce-item announce-<?= $lvl ?>">
          <div class="announce-text">
            <strong><?= esc($a['title']) ?></strong>
            <?php if (! empty($a['body'])): ?><span><?= nl2br(esc($a['body'])) ?></span><?php endif ?>
          </div>
          <span class="small muted announce-meta"><?= esc(date_id($a['starts_on'] ?? substr((string) ($a['created_at'] ?? ''), 0, 10))) ?></span>
        </li>
      <?php endforeach ?>
    </ul>
  </div>
<?php endif ?>

<?php
$kMtdLine = static function (?float $v, bool $isPct) use ($pctText): string {
    if ($v === null) {
        return '';
    }

    return '<div class="small muted">' . lang('Dashboard.mtd') . ': '
        . ($isPct ? $pctText($v) : money_c($v, false, 0)) . '</div>';
};
?>
<div class="kpis">
  <div class="kpi">
    <div class="k-label"><?= lang('Dashboard.revenue') ?> <span class="muted">YTD</span></div>
    <div class="k-value mono"><?= money_c($k['revenue'], false, 0) ?></div>
    <?php if ($k['revLyPct'] !== null): ?>
      <div class="small muted"><?= number_format($k['revLyPct'] * 100) ?>% <?= lang('Dashboard.of_ly', [$prev]) ?></div>
    <?php endif ?>
    <?= $kMtd ? $kMtdLine($kMtd['revenue'], false) : '' ?>
  </div>
  <div class="kpi">
    <div class="k-label"><?= lang('Dashboard.gop_pct') ?> <span class="muted">YTD</span></div>
    <div class="k-value mono"><?= $pctText($k['gopPct']) ?></div>
    <?= $kMtd ? $kMtdLine($kMtd['gopPct'], true) : '' ?>
  </div>
  <div class="kpi <?= $k['ebitdaPct'] < 0 ? 'neg' : 'pos' ?>">
    <div class="k-label"><?= lang('Dashboard.ebitda_pct') ?> <span class="muted">YTD</span></div>
    <div class="k-value mono"><?= $pctText($k['ebitdaPct']) ?></div>
    <?= $kMtd ? $kMtdLine($kMtd['ebitdaPct'], true) : '' ?>
  </div>
  <div class="kpi <?= $k['net'] < 0 ? 'neg' : 'pos' ?>">
    <div class="k-label"><?= lang('Dashboard.net_income') ?> <span class="muted">YTD</span></div>
    <div class="k-value mono"><?= money_c($k['net'], false, 0) ?></div>
    <?= $kMtd ? $kMtdLine($kMtd['net'], false) : '' ?>
  </div>
  <div class="kpi">
    <div class="k-label"><?= lang('Dashboard.ar') ?></div>
    <div class="k-value mono"><?= money_c($k['ar'], false, 0) ?></div>
  </div>
  <div class="kpi">
    <div class="k-label"><?= lang('Dashboard.ap') ?></div>
    <div class="k-value mono"><?= money_c($k['ap'], false, 0) ?></div>
  </div>
</div>

<?php
// ---- One-page P&L + Balance Sheet summary -------------------------------------
$curCol  = $month > 0 ? lang('Dashboard.ytd') : (string) $year;
$prevCol = ($month > 0 ? lang('Dashboard.ytd') . ' ' : '') . $prev;
?>
<div class="card">
  <div class="announce-card-head">
    <h2 style="margin:0"><?= lang('Dashboard.summary_title') ?></h2>
    <a class="btn sm ghost no-print" href="<?= site_url('reports/executive-summary?year=' . $year . ($month > 0 ? '&period=m' . $month : '')) ?>"><?= lang('Dashboard.open_report') ?></a>
  </div>
  <div class="row" style="align-items:flex-start;gap:22px">
    <div style="flex:1.15;min-width:320px;overflow-x:auto">
      <h3 style="font-size:13px;margin:4px 0 6px"><?= lang('Dashboard.summary_pl') ?></h3>
      <table class="grid tight">
        <thead>
          <tr>
            <th></th>
            <?php if ($month > 0): ?><th class="right"><?= esc($mName) ?></th><?php endif ?>
            <th class="right"><?= esc($curCol) ?></th>
            <th class="right"><?= esc($prevCol) ?></th>
            <th class="right"><?= lang('Dashboard.of_sales') ?></th>
            <th class="right"><?= lang('Dashboard.yoy') ?></th>
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
              <td<?= $isChild ? ' style="padding-left:22px"' : '' ?><?= $isChild ? ' class="muted small"' : '' ?>><?= esc($r['label']) ?></td>
              <?php if ($month > 0): ?>
                <td class="right mono <?= $isChild ? 'muted small' : '' ?>"><?= $r['mtd'] === null ? '' : money_c($r['mtd'], false, 0) ?></td>
              <?php endif ?>
              <td class="right mono <?= $isChild ? 'muted small' : '' ?>"><?= money_c($r['cur'], false, 0) ?></td>
              <td class="right mono muted <?= $isChild ? 'small' : '' ?>"><?= money_c($r['prev'], false, 0) ?></td>
              <td class="right mono <?= $isChild ? 'muted small' : '' ?>"><?= $showPct ? $pctText($r['pctCur']) : '' ?></td>
              <td class="right small"><?= $isChild ? '' : $deltaCell($r['yoy']) ?></td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>

    <div style="flex:1;min-width:280px;overflow-x:auto">
      <h3 style="font-size:13px;margin:4px 0 6px"><?= lang('Dashboard.summary_bs') ?> <span class="muted small"><?= lang('Dashboard.as_of', [date_id($sumMeta['asOf'])]) ?></span></h3>
      <table class="grid tight">
        <thead>
          <tr><th></th><th class="right"><?= esc($year) ?></th><th class="right muted"><?= esc((string) $prev) ?></th></tr>
        </thead>
        <tbody>
          <?php foreach ($summary['bs'] as $r): ?>
            <?php if ($r['level'] === 'head'): ?>
              <tr class="grp-row"><td colspan="3"><?= esc($r['label']) ?></td></tr>
            <?php else: ?>
              <?php $isTotal = $r['level'] === 'total';
              $isChild = $r['level'] === 'child'; ?>
              <tr class="<?= $isTotal ? 'subtotal' : '' ?>">
                <td<?= $isChild ? ' style="padding-left:16px"' : '' ?>><?= esc($r['label']) ?></td>
                <td class="right mono"><?= money_c($r['cur'], false, 0) ?></td>
                <td class="right mono muted"><?= money_c($r['prev'], false, 0) ?></td>
              </tr>
            <?php endif ?>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="chart-grid">
  <div class="card">
    <h2><?= lang('Dashboard.chart_sales') ?> <span class="muted small"><?= $hasPrev ? $year . ' vs ' . $prev : $year ?></span></h2>
    <?= $hasPrev
        ? Svg::groupedBars($labels, [(string) $year => $revM, (string) $prev => $revPrevM], [Svg::BRAND, Svg::MUTED])
        : Svg::signedBars($labels, $revM) ?>
    <table class="grid tight" style="margin-top:10px">
      <thead>
        <tr>
          <th><?= lang('Dashboard.col_month') ?></th>
          <th class="right"><?= esc((string) $year) ?></th>
          <th class="right muted"><?= esc((string) $prev) ?></th>
          <th class="right"><?= lang('Dashboard.col_change') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($labels as $i => $lbl): ?>
          <?php $cv = $revM[$i] ?? 0.0;
          $pv = $revPrevM[$i] ?? 0.0; ?>
          <tr>
            <td><?= esc($lbl) ?></td>
            <td class="right mono"><?= money_c($cv, false, 0) ?></td>
            <td class="right mono muted"><?= money_c($pv, false, 0) ?></td>
            <td class="right small"><?= $deltaCell($growth($cv, $pv)) ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
      <tfoot>
        <?php $ct = array_sum($revM);
        $pt = array_sum($revPrevM); ?>
        <tr>
          <td><?= lang('App.total') ?></td>
          <td class="right mono"><?= money_c($ct, false, 0) ?></td>
          <td class="right mono muted"><?= money_c($pt, false, 0) ?></td>
          <td class="right small"><?= $deltaCell($growth($ct, $pt)) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="card">
    <h2><?= lang('Dashboard.chart_gop') ?> <span class="muted small"><?= $year ?></span></h2>
    <?= Svg::barsAndLine($labels, [lang('Dashboard.sales') => $revM, lang('Dashboard.cost_of_sales') => $cosM], $gopPctM, lang('Dashboard.gop_pct')) ?>
  </div>

  <div class="card">
    <h2><?= lang('Dashboard.ebitda') ?> <span class="muted small"><?= $hasPrev ? $year . ' vs ' . $prev : $year ?></span></h2>
    <?= $hasPrev
        ? Svg::groupedBars($labels, [(string) $year => $ebitdaM, (string) $prev => $ebitdaPrevM], [Svg::BRAND, Svg::MUTED])
        : Svg::signedBars($labels, $ebitdaM) ?>
  </div>

  <div class="card">
    <h2><?= lang('Dashboard.chart_budget') ?> <span class="muted small"><?= $year ?></span></h2>
    <div class="chart-placeholder">
      <p><?= lang('Dashboard.budget_soon') ?></p>
    </div>
  </div>

  <div class="card">
    <h2><?= lang('Dashboard.chart_top_clients') ?> <span class="muted small">YTD</span></h2>
    <?= Svg::hBars($topClients) ?>
  </div>

  <div class="card">
    <h2><?= lang('Dashboard.chart_cash_move') ?> <span class="muted small"><?= $year ?></span></h2>
    <?= Svg::signedBars($labels, $cashMoveM) ?>
  </div>
</div>

<div class="card">
  <h2><?= lang('Dashboard.recent') ?> <?php if ($draftCount): ?><span class="badge badge-gray"><?= $draftCount ?> <?= lang('App.draft') ?></span><?php endif ?></h2>
  <table class="grid tight">
    <thead>
      <tr><th><?= lang('Dashboard.col_no') ?></th><th><?= lang('Dashboard.col_date') ?></th><th><?= lang('Dashboard.col_desc') ?></th><th class="right"><?= lang('Dashboard.col_amount') ?></th><th><?= lang('Dashboard.col_status') ?></th></tr>
    </thead>
    <tbody>
      <?php foreach ($recent as $j): ?>
        <tr>
          <td class="nowrap"><a href="<?= site_url('journals/' . $j['id']) ?>"><?= esc($j['journal_no']) ?></a></td>
          <td class="nowrap"><?= date_id($j['entry_date']) ?></td>
          <td><?= esc($j['description']) ?></td>
          <td class="right mono"><?= money_c($j['total_debit']) ?></td>
          <td><?= status_badge($j['status']) ?></td>
        </tr>
      <?php endforeach ?>
      <?php if (! $recent): ?>
        <tr><td colspan="5" class="muted"><?= lang('Dashboard.no_journals') ?> <a href="<?= site_url('journals/new') ?>"><?= lang('Dashboard.create_first') ?></a></td></tr>
      <?php endif ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
