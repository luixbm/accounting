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

    return $h . '<tr class="subtotal"><td>Total</td>' . $cells($g['totals']) . '</tr>';
};
?>

<div class="page-head"><h1><?= esc($title) ?></h1><div class="btn-group no-print"><a class="btn ghost" href="<?= site_url('reports') ?>">&lsaquo; All reports</a></div></div>
<?= view('reports/_period', ['f' => $f, 'showCompare' => true]) ?>

<div class="report-title">
  <div class="co"><?= esc(company_name()) ?></div>
  <h1><?= esc($title) ?></h1>
  <div class="muted"><?= date_id($f['from']) ?> — <?= date_id($f['to']) ?> · per <?= esc($f['compare']) ?></div>
</div>

<div class="card">
  <div style="overflow-x:auto">
    <table class="grid tight">
      <thead>
        <tr><th style="min-width:220px">Account</th><?php foreach ($cols as $c): ?><th class="right nowrap"><?= esc($c) ?></th><?php endforeach ?></tr>
      </thead>
      <tbody>
        <?= $section($data['groups']['operating']) ?>
        <?= $section($data['groups']['investing']) ?>
        <?= $section($data['groups']['financing']) ?>
        <tr class="subtotal" style="background:var(--brand-soft)"><td>NET CHANGE IN CASH</td><?= $cells($data['net_change']) ?></tr>
        <tr><td>Cash — beginning</td><?= $cells($data['opening']) ?></tr>
      </tbody>
      <tfoot>
        <tr><td>CASH — END</td><?= $cells($data['closing']) ?></tr>
      </tfoot>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
