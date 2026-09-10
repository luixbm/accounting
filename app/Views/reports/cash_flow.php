<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$section = static function (array $g): string {
    $h = '<tr class="grp-row"><td colspan="2">' . esc($g['label']) . '</td></tr>';
    if (! $g['rows']) {
        $h .= '<tr><td class="muted" colspan="2">—</td></tr>';
    }
    foreach ($g['rows'] as $r) {
        $h .= '<tr><td><span class="mono small">' . esc($r['code']) . '</span> ' . esc($r['name']) . '</td>'
            . '<td class="right mono">' . money($r['amount']) . '</td></tr>';
    }

    return $h . '<tr class="subtotal"><td>' . lang('App.total') . '</td><td class="right mono">' . money($g['total']) . '</td></tr>';
};
?>

<div class="page-head"><h1><?= esc($title) ?></h1><div class="btn-group no-print"><a class="btn ghost" href="<?= site_url('reports') ?>">&lsaquo; <?= lang('Report.v_all_reports') ?></a></div></div>
<?= view('reports/_period', ['f' => $f, 'showCompare' => true]) ?>

<div class="report-title">
  <?php if ($u = company_logo_url()): ?><img src="<?= esc($u) ?>"><?php endif ?>
  <div class="co"><?= esc(company_legal_name()) ?></div>
  <h1><?= esc($title) ?></h1>
  <div class="muted"><?= date_id($f['from']) ?> — <?= date_id($f['to']) ?> · <?= lang('Report.v_direct_method') ?></div>
</div>

<div class="kpis">
  <div class="kpi"><div class="k-label"><?= lang('Report.v_cash_beginning') ?></div><div class="k-value mono"><?= rupiah($data['opening'], false, 0) ?></div></div>
  <div class="kpi <?= $data['operating'] < 0 ? 'neg' : 'pos' ?>"><div class="k-label"><?= lang('Report.v_operating') ?></div><div class="k-value mono"><?= rupiah($data['operating'], false, 0) ?></div></div>
  <div class="kpi <?= $data['investing'] < 0 ? 'neg' : 'pos' ?>"><div class="k-label"><?= lang('Report.v_investing') ?></div><div class="k-value mono"><?= rupiah($data['investing'], false, 0) ?></div></div>
  <div class="kpi <?= $data['financing'] < 0 ? 'neg' : 'pos' ?>"><div class="k-label"><?= lang('Report.v_financing') ?></div><div class="k-value mono"><?= rupiah($data['financing'], false, 0) ?></div></div>
  <div class="kpi"><div class="k-label"><?= lang('Report.v_cash_end') ?></div><div class="k-value mono"><?= rupiah($data['closing'], false, 0) ?></div></div>
</div>

<div class="card" style="max-width:720px">
  <table class="grid tight">
    <tbody>
      <?= $section($data['groups']['operating']) ?>
      <?= $section($data['groups']['investing']) ?>
      <?= $section($data['groups']['financing']) ?>
      <tr class="subtotal" style="background:var(--brand-soft)"><td><?= lang('Report.v_net_change_cash') ?></td><td class="right mono"><?= money($data['net_change']) ?></td></tr>
      <tr><td><?= lang('Report.v_cash_at_beginning') ?></td><td class="right mono"><?= money($data['opening']) ?></td></tr>
    </tbody>
    <tfoot>
      <tr><td><?= lang('Report.v_cash_at_end') ?></td><td class="right mono"><?= money($data['closing']) ?></td></tr>
    </tfoot>
  </table>
  <p class="small <?= $data['reconciles'] ? 'muted' : '' ?>" style="<?= $data['reconciles'] ? '' : 'color:var(--red);font-weight:700' ?>">
    <?= $data['reconciles']
        ? lang('Report.v_cf_ok')
        : lang('Report.v_cf_off', [money(($data['opening'] + $data['net_change']) - $data['closing'])]) ?>
  </p>
</div>

<?= $this->endSection() ?>
