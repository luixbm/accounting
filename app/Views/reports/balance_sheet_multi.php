<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
$cols = $data['columns'];

$cells = static function (array $amounts): string {
    $h = '';
    foreach ($amounts as $v) {
        $h .= '<td class="right mono">' . money($v, 2, true) . '</td>';
    }

    return $h;
};

$section = static function (array $group) use ($cells): string {
    $span = count($group['totals']) + 1;
    $h    = '<tr class="grp-row"><td colspan="' . $span . '">' . esc($group['label']) . '</td></tr>';
    foreach ($group['blocks'] as $blk) {
        $named = $blk['code'] !== '';
        if ($named) {
            $h .= '<tr class="hdr-row"><td colspan="' . $span . '"><span class="mono small">' . esc($blk['code']) . '</span> ' . esc($blk['name']) . '</td></tr>';
        }
        foreach ($blk['rows'] as $r) {
            $lbl = ($r['code'] !== '' ? '<span class="mono small">' . esc($r['code']) . '</span> ' : '') . esc($r['name']);
            $h  .= '<tr><td' . ($named ? ' style="padding-left:28px"' : '') . '>' . $lbl . '</td>' . $cells($r['amounts']) . '</tr>';
        }
        if ($named) {
            $h .= '<tr class="sub-row"><td class="right">Subtotal — ' . esc($blk['name']) . '</td>' . $cells($blk['subtotals']) . '</tr>';
        }
    }
    $h .= '<tr class="subtotal"><td>Total</td>' . $cells($group['totals']) . '</tr>';

    return $h;
};
?>

<div class="page-head"><h1>Balance Sheet — comparative</h1><div class="btn-group no-print"><a class="btn ghost" href="<?= site_url('reports') ?>">&lsaquo; All reports</a></div></div>
<?= view('reports/_period', ['f' => $f, 'showAsOf' => true, 'showCompare' => true, 'showZeros' => true]) ?>

<div class="report-title">
  <div class="co"><?= esc(company_name()) ?></div>
  <h1>Neraca / Balance Sheet</h1>
  <div class="muted">Snapshot per period end · <?= date_id($from) ?> — <?= date_id($to) ?> · per <?= esc($compare) ?></div>
</div>

<div class="card">
  <div style="overflow-x:auto">
    <table class="grid tight">
      <thead>
        <tr>
          <th style="min-width:220px">Account</th>
          <?php foreach ($cols as $c): ?><th class="right nowrap"><?= esc($c) ?></th><?php endforeach ?>
        </tr>
      </thead>
      <tbody>
        <?= $section($data['groups']['asset']) ?>
        <tr class="subtotal" style="background:#eef4ff"><td>TOTAL AKTIVA / ASSETS</td><?= $cells($data['assets']) ?></tr>
        <?= $section($data['groups']['liability']) ?>
        <?= $section($data['groups']['equity']) ?>
        <tr class="subtotal" style="background:#eef4ff"><td>TOTAL KEWAJIBAN &amp; EKUITAS</td><?= $cells($data['liab_equity']) ?></tr>
      </tbody>
      <tfoot>
        <tr>
          <td>Selisih / Difference</td>
          <?php foreach ($cols as $i => $_): ?>
            <?php $diff = $data['assets'][$i] - $data['liab_equity'][$i]; ?>
            <td class="right mono" style="<?= abs($diff) < 0.5 ? '' : 'color:var(--red);font-weight:700' ?>"><?= money($diff, 2, true) ?></td>
          <?php endforeach ?>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
