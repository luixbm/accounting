<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
/**
 * @var list<array<string,mixed>>            $batches
 * @var array<int,list<array<string,mixed>>> $itemsByBatch
 * @var string                               $status
 * @var string                               $q
 */
$badge = static function (array $it): array {
    if (! empty($it['confirmed_at'])) {
        return ['badge-green', lang('Review.status_confirmed')];
    }

    return match ($it['match_status']) {
        'would_apply', 'unchanged' => ! empty($it['over_budget'])
            ? ['badge-amber', lang('Review.status_overbudget')]
            : ['badge-green', lang('Review.status_matched_ok')],
        'ambiguous', 'not_found' => ['badge-gray', lang('Review.status_pending')],
        'applied'                => ['badge-green', lang('Review.status_confirmed')],
        default                  => ['badge-red', lang('Review.status_error')],
    };
};
$canAct = static fn (array $it): bool => empty($it['confirmed_at']) && in_array($it['match_status'], ['would_apply', 'unchanged'], true);
$diff   = static fn (?float $budget, ?float $req): ?float => $budget === null || $req === null ? null : round($budget - $req, 2);

$promiseCell = static function (array $it): string {
    if (! empty($it['confirmed_at'])) {
        return '<span class="mono">' . esc($it['promise_date'] ? date_id($it['promise_date']) : '—') . '</span>';
    }

    return '<form method="post" action="' . site_url('purchases/review/' . $it['id'] . '/promise-date') . '" style="display:inline">'
        . csrf_field()
        . '<input type="date" name="promise_date" value="' . esc($it['promise_date'] ?? '', 'attr') . '" onchange="this.form.requestSubmit()" style="padding:2px 4px;font-size:.82rem">'
        . '</form>';
};

$actionCell = static function (array $it) use ($canAct): string {
    if ($canAct($it)) {
        return '<form method="post" action="' . site_url('purchases/review/' . $it['id'] . '/confirm') . '" style="display:inline">' . csrf_field()
            . '<button class="btn sm" type="submit">' . esc(lang('Review.confirm')) . '</button></form>';
    }
    if (empty($it['confirmed_at'])) {
        return '<form method="post" action="' . site_url('purchases/review/' . $it['id'] . '/recheck') . '" style="display:inline">' . csrf_field()
            . '<button class="btn sm ghost" type="submit">' . esc(lang('Review.recheck')) . '</button></form>';
    }

    return '';
};

$deleteBtn = static function (int $batchId): string {
    return '<form method="post" action="' . site_url('purchases/review/batch/' . $batchId . '/delete') . '" style="display:inline" onsubmit="return confirm(' . esc(json_encode(lang('Review.delete_confirm')), 'attr') . ')">'
        . csrf_field()
        . '<button class="btn sm ghost" type="submit" title="' . esc(lang('Review.delete'), 'attr') . '">&#128465;</button></form>';
};
?>

<div class="page-head">
  <div><h1><?= esc(lang('Review.title')) ?></h1><div class="muted small"><?= esc(lang('Review.subtitle')) ?></div></div>
  <div class="btn-group no-print">
    <form method="get" action="<?= site_url('purchases/review') ?>" style="display:contents">
      <input type="hidden" name="status" value="<?= esc($status, 'attr') ?>">
      <input type="search" name="q" value="<?= esc($q, 'attr') ?>" placeholder="<?= esc(lang('Review.search_ph'), 'attr') ?>" style="min-width:180px;width:auto">
      <button class="btn sm ghost" type="submit"><?= esc(lang('Review.search')) ?></button>
    </form>
    <a class="btn sm <?= $status === 'open' ? '' : 'ghost' ?>" href="<?= site_url('purchases/review?status=open' . ($q !== '' ? '&q=' . urlencode($q) : '')) ?>"><?= esc(lang('Review.filter_open')) ?></a>
    <a class="btn sm <?= $status === 'all' ? '' : 'ghost' ?>" href="<?= site_url('purchases/review?status=all' . ($q !== '' ? '&q=' . urlencode($q) : '')) ?>"><?= esc(lang('Review.filter_all')) ?></a>
  </div>
</div>

<div class="card">
  <div style="overflow-x:auto">
    <table class="grid tight" id="reviewTable">
      <thead>
        <tr>
          <th><?= esc(lang('Review.col_supplier_file')) ?></th>
          <th><?= esc(lang('Review.col_travelers')) ?></th>
          <th><?= esc(lang('Review.col_service_date')) ?></th>
          <th><?= esc(lang('Review.col_arrival_date')) ?></th>
          <th class="right"><?= esc(lang('Review.col_requested')) ?></th>
          <th class="right"><?= esc(lang('Review.col_matched_budget')) ?></th>
          <th class="right"><?= esc(lang('Review.col_diff')) ?></th>
          <th><?= esc(lang('Review.col_status')) ?></th>
          <th><?= esc(lang('Review.col_promise_date')) ?></th>
          <th class="no-print"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($batches as $b): ?>
          <?php
            $items = $itemsByBatch[$b['id']] ?? [];
            if (! $items) {
                continue;
            }
          ?>
          <?php if (count($items) === 1): ?>
            <?php $it = $items[0]; [$cls, $label] = $badge($it); $d = $diff($it['matched_budget'] !== null ? (float) $it['matched_budget'] : null, $it['requested_amount'] !== null ? (float) $it['requested_amount'] : null); ?>
            <tr>
              <td><b><?= esc($b['vendor'] ?: $it['party_name'] ?: '—') ?></b><?php if ($b['file_name']): ?><br><span class="muted small mono"><?= esc($b['file_name']) ?><?php if ($b['file_url']): ?> · <a href="<?= esc($b['file_url'], 'attr') ?>" target="_blank" rel="noopener">&#8599;</a><?php endif ?></span><?php endif ?></td>
              <td><?= esc($it['party_name'] ?: '—') ?><?php if ($it['description']): ?><br><span class="muted small"><?= esc($it['description']) ?></span><?php endif ?></td>
              <td class="mono small"><?= $it['service_date'] ? date_id($it['service_date']) : '—' ?></td>
              <td class="mono small"><?= $it['arrival_date'] ? date_id($it['arrival_date']) : '—' ?></td>
              <td class="right mono"><?= $it['requested_amount'] !== null ? money((float) $it['requested_amount']) : '—' ?></td>
              <td class="right mono"><?= $it['matched_budget'] !== null ? money((float) $it['matched_budget']) : '—' ?></td>
              <td class="right mono" style="color:<?= $d === null ? 'inherit' : ($d < 0 ? 'var(--red)' : 'var(--green)') ?>"><?= $d === null ? '—' : money($d) ?></td>
              <td><span class="badge <?= $cls ?>"><?= esc($label) ?></span><?php if ($it['match_message']): ?><br><span class="muted small"><?= esc($it['match_message']) ?></span><?php endif ?></td>
              <td><?= $promiseCell($it) ?></td>
              <td class="right no-print"><div class="btn-group"><?= $actionCell($it) . $deleteBtn((int) $b['id']) ?></div></td>
            </tr>
          <?php else: ?>
            <?php
              $sumReq  = array_sum(array_column($items, 'requested_amount'));
              $sumBud  = array_sum(array_map(static fn ($i) => $i['matched_budget'] !== null ? (float) $i['matched_budget'] : 0.0, $items));
              $sumOk   = abs($sumReq - $sumBud) < 0.005;
              $done    = count(array_filter($items, static fn ($i) => ! empty($i['confirmed_at'])));
              $grpId   = 'grp-' . $b['id'];
              $anyAct  = (bool) array_filter($items, $canAct);
            ?>
            <tr class="grp-row" data-toggle="<?= $grpId ?>" style="cursor:pointer">
              <td><span class="grp-arrow">&#9662;</span> <b><?= esc($b['vendor'] ?: $b['file_name'] ?: '—') ?></b><br>
                <span class="muted small mono"><?= esc($b['file_name'] ?: '') ?><?= $b['file_name'] ? ' · ' : '' ?><?= esc(lang('Review.transactions_n', [count($items)])) ?> · <?= esc(lang('Review.confirmed_of', [$done, count($items)])) ?></span>
              </td>
              <td></td>
              <td></td>
              <td></td>
              <td class="right mono"><?= money((float) $sumReq) ?></td>
              <td class="right mono"><?= money((float) $sumBud) ?></td>
              <td></td>
              <td><span class="badge <?= $sumOk ? 'badge-green' : 'badge-amber' ?>"><?= esc($sumOk ? lang('Review.sum_ok') : lang('Review.sum_mismatch')) ?></span></td>
              <td></td>
              <td class="right no-print">
                <div class="btn-group" onclick="event.stopPropagation()">
                  <?php if ($done < count($items)): ?>
                    <form method="post" action="<?= site_url('purchases/review/batch/' . $b['id'] . '/recheck') ?>"><?= csrf_field() ?><button class="btn sm ghost" type="submit"><?= esc(lang('Review.recheck_all')) ?></button></form>
                  <?php endif ?>
                  <?php if ($anyAct): ?>
                    <form method="post" action="<?= site_url('purchases/review/batch/' . $b['id'] . '/confirm') ?>"><?= csrf_field() ?><button class="btn sm" type="submit"><?= esc(lang('Review.confirm_all')) ?></button></form>
                  <?php endif ?>
                  <?= $deleteBtn((int) $b['id']) ?>
                </div>
              </td>
            </tr>
            <?php foreach ($items as $it): ?>
              <?php [$cls, $label] = $badge($it); $d = $diff($it['matched_budget'] !== null ? (float) $it['matched_budget'] : null, $it['requested_amount'] !== null ? (float) $it['requested_amount'] : null); ?>
              <tr class="<?= $grpId ?>">
                <td class="muted small" style="padding-left:24px"><?= esc($it['description'] ?: '—') ?></td>
                <td><?= esc($it['party_name'] ?: '—') ?></td>
                <td class="mono small"><?= $it['service_date'] ? date_id($it['service_date']) : '—' ?></td>
                <td class="mono small"><?= $it['arrival_date'] ? date_id($it['arrival_date']) : '—' ?></td>
                <td class="right mono"><?= $it['requested_amount'] !== null ? money((float) $it['requested_amount']) : '—' ?></td>
                <td class="right mono"><?= $it['matched_budget'] !== null ? money((float) $it['matched_budget']) : '—' ?></td>
                <td class="right mono" style="color:<?= $d === null ? 'inherit' : ($d < 0 ? 'var(--red)' : 'var(--green)') ?>"><?= $d === null ? '—' : money($d) ?></td>
                <td><span class="badge <?= $cls ?>"><?= esc($label) ?></span><?php if ($it['match_message']): ?><br><span class="muted small"><?= esc($it['match_message']) ?></span><?php endif ?></td>
                <td><?= $promiseCell($it) ?></td>
                <td class="right no-print"><?= $actionCell($it) ?></td>
              </tr>
            <?php endforeach ?>
          <?php endif ?>
        <?php endforeach ?>
        <?php if (! $batches): ?><tr><td colspan="10" class="muted"><?= esc(lang('Review.no_batches')) ?></td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<style>
  .grp-row{background:var(--panel-2, #f5f5f7)}
  .grp-row td{font-weight:600}
</style>
<script>
(function(){
  document.querySelectorAll('[data-toggle]').forEach(function(head){
    head.addEventListener('click', function(){
      var key = head.getAttribute('data-toggle');
      var hidden = null;
      document.querySelectorAll('.' + key).forEach(function(row){
        if (hidden === null) { hidden = row.hidden = !row.hidden; } else { row.hidden = hidden; }
      });
      var arrow = head.querySelector('.grp-arrow');
      if (arrow) { arrow.innerHTML = hidden ? '&#9656;' : '&#9662;'; }
    });
  });
})();
</script>

<?= $this->endSection() ?>
