<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$cols  = $data['columns'];
$cells = static function (array $amounts): string {
    $h = '';
    foreach ($amounts as $v) {
        $h .= '<td class="right mono">' . money($v, 2, true) . '</td>';
    }

    return $h;
};
$section = static function (array $g) use ($cells): string {
    $h = '<tr class="grp-row"><td colspan="' . (count($g['totals']) + 1) . '">' . esc($g['label']) . '</td></tr>';
    foreach ($g['rows'] as $r) {
        $h .= '<tr><td><span class="mono small">' . esc($r['code']) . '</span> ' . esc($r['name']) . '</td>' . $cells($r['amounts']) . '</tr>';
    }

    return $h . '<tr class="subtotal"><td>' . lang('App.total') . '</td>' . $cells($g['totals']) . '</tr>';
};
?>

<div class="page-head"><h1><?= esc($title) ?></h1><div class="btn-group no-print"><a class="btn ghost" href="<?= site_url('reports') ?>">&lsaquo; <?= lang('Report.v_all_reports') ?></a></div></div>
<?= view('reports/_period', ['f' => $f, 'showCompare' => true]) ?>

<div class="report-title">
  <div class="co"><?= esc(company_legal_name()) ?></div>
  <h1><?= esc($title) ?></h1>
  <div class="muted"><?= date_id($f['from']) ?> — <?= date_id($f['to']) ?> · <?= lang('App.' . $f['compare']) ?></div>
</div>

<div class="card">
  <div style="overflow-x:auto">
    <table class="grid tight">
      <thead>
        <tr><th style="min-width:220px"><?= lang('App.account') ?></th><?php foreach ($cols as $c): ?><th class="right nowrap"><?= esc($c) ?></th><?php endforeach ?></tr>
      </thead>
      <tbody>
        <?= $section($data['groups']['operating']) ?>
        <?= $section($data['groups']['investing']) ?>
        <?= $section($data['groups']['financing']) ?>
        <tr class="subtotal" style="background:var(--brand-soft)"><td><?= lang('Report.v_net_change_cash') ?></td><?= $cells($data['net_change']) ?></tr>
        <tr><td><?= lang('Report.v_cash_beginning') ?></td><?= $cells($data['opening']) ?></tr>
      </tbody>
      <tfoot>
        <tr><td><?= lang('Report.v_cash_end') ?></td><?= $cells($data['closing']) ?></tr>
      </tfoot>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
