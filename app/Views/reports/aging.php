<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $t = $data['totals']; ?>

<div class="page-head"><h1><?= esc($title) ?></h1><div class="btn-group no-print"><a class="btn ghost" href="<?= site_url('reports') ?>">&lsaquo; <?= lang('Report.v_all_reports') ?></a></div></div>
<?= view('reports/_period', ['f' => $f, 'showAsOf' => true]) ?>

<div class="report-title">
  <div class="co"><?= esc(company_legal_name()) ?></div>
  <h1><?= esc($heading) ?> — <?= lang('Report.v_aging_suffix') ?></h1>
  <div class="muted"><?= lang('App.as_of', [date_id($asOf)]) ?> &middot; <?= lang('Report.v_bucketed_note') ?></div>
</div>

<div class="card">
  <table class="grid tight mono">
    <thead>
      <tr>
        <th style="font-family:sans-serif"><?= $kind === 'customer' ? lang('Report.v_customer') : lang('Report.v_supplier') ?></th>
        <th class="right"><?= lang('Report.v_current') ?></th><th class="right">1–30</th><th class="right">31–60</th>
        <th class="right">61–90</th><th class="right"><?= lang('Report.v_over90') ?></th><th class="right"><?= lang('App.total') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($data['rows'] as $r): ?>
        <tr>
          <td style="font-family:sans-serif"><?= esc($r['name']) ?></td>
          <td class="right"><?= money($r['current'], 2, true) ?></td>
          <td class="right"><?= money($r['b30'], 2, true) ?></td>
          <td class="right"><?= money($r['b60'], 2, true) ?></td>
          <td class="right"><?= money($r['b90'], 2, true) ?></td>
          <td class="right"><?= money($r['b90p'], 2, true) ?></td>
          <td class="right"><strong><?= money($r['total']) ?></strong></td>
        </tr>
      <?php endforeach ?>
      <?php if (! $data['rows']): ?><tr><td colspan="7" class="muted" style="font-family:sans-serif"><?= lang('Report.v_empty_outstanding') ?></td></tr><?php endif ?>
    </tbody>
    <tfoot>
      <tr>
        <td style="font-family:sans-serif">TOTAL</td>
        <td class="right"><?= money($t['current']) ?></td>
        <td class="right"><?= money($t['b30']) ?></td>
        <td class="right"><?= money($t['b60']) ?></td>
        <td class="right"><?= money($t['b90']) ?></td>
        <td class="right"><?= money($t['b90p']) ?></td>
        <td class="right"><?= money($t['total']) ?></td>
      </tr>
    </tfoot>
  </table>
</div>

<?= $this->endSection() ?>
