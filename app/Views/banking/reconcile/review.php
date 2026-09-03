<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php
/** @var array $st @var array $bank @var array $sum @var list<array> $freeBook @var list<array> $expenseAccounts */
$reconciled = $st['status'] === 'reconciled';
$canPost    = user_can('journal.post');

// index book lines by id for the "matched to" display
$bookById = [];
foreach ($sum['book'] as $b) {
    $bookById[(int) $b['id']] = $b;
}

$bookOpt = static function ($rows) {
    $h = '<option value="">' . lang('Import.bk_match_to_book') . '</option>';
    foreach ($rows as $b) {
        $h .= '<option value="' . (int) $b['id'] . '">'
            . esc(date_id($b['entry_date']) . '  ' . $b['journal_no'] . '  ' . money_c($b['effect'])
                  . ($b['memo'] ? '  · ' . mb_substr((string) $b['memo'], 0, 40) : ''))
            . '</option>';
    }

    return $h;
};
$accOpt = static function ($rows) {
    $h = '<option value="">' . lang('Import.bk_offset_acct') . '</option>';
    foreach ($rows as $a) {
        $h .= '<option value="' . (int) $a['id'] . '">' . esc($a['code'] . ' · ' . $a['name']) . '</option>';
    }

    return $h;
};
?>

<div class="page-head">
  <div>
    <h1><?= lang('Import.bk_reconcile_h') ?> — <?= esc($bank['name'] ?? '') ?>
      <?= $reconciled ? '<span class="badge badge-green">' . esc(lang('Import.bk_reconciled')) . '</span>' : '<span class="badge badge-gray">' . esc(lang('Import.bk_draft')) . '</span>' ?>
    </h1>
    <div class="muted small">
      <?= lang('Import.bk_stmt_to', [date_id($st['statement_date'])]) ?> ·
      <?= lang('Import.bk_opening_lc') ?> <?= money_c($st['opening_balance']) ?> · <?= lang('Import.bk_closing_lc') ?> <?= money_c($st['closing_balance']) ?>
      <?= $st['note'] ? ' · ' . esc($st['note']) : '' ?>
    </div>
  </div>
  <div class="btn-group no-print">
    <a class="btn ghost" href="<?= site_url('banking/reconcile') ?>">&lsaquo; <?= lang('Import.bk_all_stmts') ?></a>
    <a class="btn ghost" href="<?= site_url('banking/reconcile/' . $st['id'] . '/report') ?>"><?= lang('Import.bk_print_stmt') ?></a>
    <?php if ($canPost && ! $reconciled): ?>
      <a class="btn ghost" href="<?= site_url('banking/reconcile/' . $st['id'] . '/map') ?>"><?= lang('Import.bk_remap') ?></a>
      <form method="post" action="<?= site_url('banking/reconcile/' . $st['id'] . '/rematch') ?>" style="display:inline"><?= csrf_field() ?><button class="btn secondary"><?= lang('Import.bk_automatch_again') ?></button></form>
    <?php endif ?>
  </div>
</div>

<?php
$tiles = [
    [lang('Import.bk_t_book_balance'), money_c($sum['book_balance']), ''],
    [lang('Import.bk_t_stmt_closing'), money_c($st['closing_balance']), ''],
    [lang('Import.bk_t_unmatched_bank'), money_c($sum['unmatched_stmt_total']) . ' · ' . $sum['counts']['stmt_unmatched'], 'warn'],
    [lang('Import.bk_t_outstanding_books'), money_c($sum['unmatched_book_total']) . ' · ' . $sum['counts']['book_unmatched'], 'warn'],
    [lang('Import.bk_t_difference'), money_c($sum['difference']), $sum['reconciled'] ? 'ok' : 'bad'],
];
?>
<div class="kpi-row">
  <?php foreach ($tiles as [$label, $val, $tone]): ?>
    <div class="kpi <?= $tone ?>"><div class="kpi-label"><?= esc($label) ?></div><div class="kpi-val mono"><?= $val ?></div></div>
  <?php endforeach ?>
</div>

<?php if (abs((float) $sum['import_check']) >= 0.5): ?>
  <div class="alert alert-error no-print">
    <?= lang('Import.bk_import_check', [money_c($sum['import_check'])]) ?>
  </div>
<?php endif ?>

<?php if ($sum['reconciled'] && ! $reconciled && $canPost): ?>
  <form method="post" action="<?= site_url('banking/reconcile/' . $st['id'] . '/finish') ?>" class="no-print" style="margin-bottom:14px">
    <?= csrf_field() ?>
    <div class="alert alert-success"><?= lang('Import.bk_diff_zero') ?> <button class="btn sm"><?= lang('Import.bk_mark_reconciled') ?></button></div>
  </form>
<?php endif ?>
<?php if ($reconciled && $canPost): ?>
  <form method="post" action="<?= site_url('banking/reconcile/' . $st['id'] . '/reopen') ?>" class="no-print" style="margin-bottom:14px">
    <?= csrf_field() ?>
    <button class="btn sm ghost"><?= lang('Import.bk_reopen_edit') ?></button>
  </form>
<?php endif ?>

<div class="card">
  <h2><?= lang('Import.bk_stmt_lines_h') ?> <span class="muted small">(<?= $sum['counts']['stmt'] ?>)</span></h2>
  <div style="overflow-x:auto">
    <table class="grid tight">
      <thead>
        <tr><th><?= lang('App.date') ?></th><th><?= lang('App.description') ?></th><th><?= lang('Import.bk_c_ref') ?></th><th class="right"><?= lang('App.amount') ?></th><th><?= lang('Import.bk_status_action') ?></th></tr>
      </thead>
      <tbody>
        <?php foreach ($sum['stmt_lines'] as $sl): ?>
          <?php $mid = $sl['matched_line_id'] !== null ? (int) $sl['matched_line_id'] : null; ?>
          <tr>
            <td class="nowrap"><?= date_id($sl['txn_date']) ?></td>
            <td><?= esc($sl['description']) ?></td>
            <td class="muted small"><?= esc($sl['reference']) ?></td>
            <td class="right mono"><?= money_c($sl['amount']) ?></td>
            <td>
              <?php if ($mid !== null): ?>
                <span class="badge badge-green"><?= lang('Import.bk_matched') ?></span>
                <?php if (isset($bookById[$mid])): ?>
                  <a class="mono small" href="<?= site_url('journals/' . $bookById[$mid]['journal_id']) ?>"><?= esc($bookById[$mid]['journal_no']) ?></a>
                <?php endif ?>
                <span class="muted small">(<?= esc($sl['match_type']) ?>)</span>
                <?php if ($canPost && ! $reconciled): ?>
                  <form method="post" action="<?= site_url('banking/reconcile/' . $st['id'] . '/unmatch') ?>" style="display:inline">
                    <?= csrf_field() ?><input type="hidden" name="stmt_line_id" value="<?= $sl['id'] ?>">
                    <button class="btn sm ghost"><?= lang('Import.bk_unmatch') ?></button>
                  </form>
                <?php endif ?>
              <?php elseif ($canPost && ! $reconciled): ?>
                <div class="recon-actions">
                  <form method="post" action="<?= site_url('banking/reconcile/' . $st['id'] . '/match') ?>" class="inline-form">
                    <?= csrf_field() ?><input type="hidden" name="stmt_line_id" value="<?= $sl['id'] ?>">
                    <select name="book_line_id"><?= $bookOpt($freeBook) ?></select>
                    <button class="btn sm"><?= lang('Import.bk_match') ?></button>
                  </form>
                  <form method="post" action="<?= site_url('banking/reconcile/' . $st['id'] . '/add-entry') ?>" class="inline-form">
                    <?= csrf_field() ?><input type="hidden" name="stmt_line_id" value="<?= $sl['id'] ?>">
                    <select name="account_id"><?= $accOpt($expenseAccounts) ?></select>
                    <input name="memo" placeholder="<?= esc(lang('Import.bk_memo_ph'), 'attr') ?>" value="<?= esc($sl['description']) ?>">
                    <button class="btn sm secondary"><?= lang('Import.bk_add_to_books') ?></button>
                  </form>
                </div>
              <?php else: ?>
                <span class="badge badge-gray"><?= lang('Import.bk_unmatched') ?></span>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
        <?php if (! $sum['stmt_lines']): ?><tr><td colspan="5" class="muted"><?= lang('Import.bk_no_stmt_lines') ?></td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <h2><?= lang('Import.bk_outstanding_h') ?> <span class="muted small">(<?= lang('Import.bk_outstanding_sub', [$sum['counts']['book_unmatched']]) ?>)</span></h2>
  <div style="overflow-x:auto">
    <table class="grid tight">
      <thead><tr><th><?= lang('App.date') ?></th><th><?= lang('Import.bk_c_journal') ?></th><th><?= lang('Import.bk_c_memo') ?></th><th class="right"><?= lang('App.amount') ?></th></tr></thead>
      <tbody>
        <?php foreach ($sum['unmatched_book'] as $b): ?>
          <tr>
            <td class="nowrap"><?= date_id($b['entry_date']) ?></td>
            <td class="mono"><a href="<?= site_url('journals/' . $b['journal_id']) ?>"><?= esc($b['journal_no']) ?></a></td>
            <td><?= esc($b['memo'] ?: $b['jdesc']) ?></td>
            <td class="right mono"><?= money_c($b['effect']) ?></td>
          </tr>
        <?php endforeach ?>
        <?php if (! $sum['unmatched_book']): ?><tr><td colspan="4" class="muted"><?= lang('Import.bk_nothing_outstanding') ?></td></tr><?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($canPost): ?>
  <form method="post" action="<?= site_url('banking/reconcile/' . $st['id'] . '/delete') ?>" class="no-print"
        onsubmit="return confirm('<?= esc(lang('Import.bk_delete_confirm'), 'js') ?>');">
    <?= csrf_field() ?>
    <button class="btn sm ghost" style="color:var(--c-danger,#b00)"><?= lang('Import.bk_delete_stmt') ?></button>
  </form>
<?php endif ?>

<style>
  .recon-actions{display:flex;flex-direction:column;gap:6px}
  .inline-form{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
  .inline-form select,.inline-form input{max-width:240px}
  .kpi-row{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px}
  .kpi{flex:1;min-width:150px;padding:12px 14px;border:1px solid var(--c-border,#ddd);border-radius:10px;background:var(--c-surface,#fff)}
  .kpi-label{font-size:.8rem;color:var(--c-muted,#777)}
  .kpi-val{font-size:1.15rem;margin-top:4px}
  .kpi.ok{border-color:#1a7f37}.kpi.bad{border-color:#b00}.kpi.warn{border-color:#b7791f}
</style>

<?= $this->endSection() ?>
