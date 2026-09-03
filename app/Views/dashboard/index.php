<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php

use App\Libraries\Chart\Svg;

$pctText = static fn (float $f): string => number_format($f * 100, 1) . '%';
?>

<div class="page-head">
  <div>
    <h1><?= lang('Dashboard.title') ?></h1>
    <form method="get" class="inline small no-print" style="margin-top:6px">
      <span class="muted"><?= lang('App.year') ?></span>
      <select name="year" onchange="this.form.submit()" style="width:auto;padding:4px 7px">
        <?php foreach ($years as $y): ?>
          <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option>
        <?php endforeach ?>
      </select>
      <span class="muted"><?= lang('Dashboard.ytd_through', [date_id($ytdTo)]) ?></span>
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

<div class="kpis">
  <div class="kpi">
    <div class="k-label"><?= lang('Dashboard.revenue') ?> <span class="muted">YTD</span></div>
    <div class="k-value mono"><?= rupiah($k['revenue'], false, 0) ?></div>
    <?php if ($k['revLyPct'] !== null): ?>
      <div class="small muted"><?= number_format($k['revLyPct'] * 100) ?>% <?= lang('Dashboard.of_ly', [$prev]) ?></div>
    <?php endif ?>
  </div>
  <div class="kpi">
    <div class="k-label"><?= lang('Dashboard.gop_pct') ?> <span class="muted">YTD</span></div>
    <div class="k-value mono"><?= $pctText($k['gopPct']) ?></div>
  </div>
  <div class="kpi <?= $k['ebitda'] < 0 ? 'neg' : 'pos' ?>">
    <div class="k-label"><?= lang('Dashboard.ebitda') ?> <span class="muted">YTD</span></div>
    <div class="k-value mono"><?= rupiah($k['ebitda'], false, 0) ?></div>
  </div>
  <div class="kpi <?= $k['net'] < 0 ? 'neg' : 'pos' ?>">
    <div class="k-label"><?= lang('Dashboard.net_income') ?> <span class="muted">YTD</span></div>
    <div class="k-value mono"><?= rupiah($k['net'], false, 0) ?></div>
  </div>
  <div class="kpi">
    <div class="k-label"><?= lang('Dashboard.ar') ?></div>
    <div class="k-value mono"><?= rupiah($k['ar'], false, 0) ?></div>
  </div>
  <div class="kpi">
    <div class="k-label"><?= lang('Dashboard.ap') ?></div>
    <div class="k-value mono"><?= rupiah($k['ap'], false, 0) ?></div>
  </div>
</div>

<div class="chart-grid">
  <div class="card">
    <h2><?= lang('Dashboard.chart_sales') ?> <span class="muted small"><?= $hasPrev ? $year . ' vs ' . $prev : $year ?></span></h2>
    <?= $hasPrev
        ? Svg::groupedBars($labels, [(string) $year => $revM, (string) $prev => $revPrevM], [Svg::BRAND, Svg::MUTED])
        : Svg::signedBars($labels, $revM) ?>
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
          <td class="right mono"><?= rupiah($j['total_debit']) ?></td>
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
