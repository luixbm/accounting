<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $isForeign = (int) ($journal['currency_is_base'] ?? 1) === 0; ?>

<div class="page-head">
  <div>
    <h1><?= esc($journal['journal_no']) ?> <?= status_badge($journal['status']) ?></h1>
    <div class="muted small">
      <?= date_id($journal['entry_date']) ?> ·
      <?= esc($journal['currency_code']) ?><?= $isForeign ? ' @ ' . money($journal['exchange_rate'], 4) : '' ?> ·
      <?= lang('Txn.created_by_user', [esc($journal['created_by'])]) ?>
      <?php if ($journal['posted_at']): ?> · <?= lang('Txn.posted_on', [esc($journal['posted_at'])]) ?><?php endif ?>
    </div>
  </div>
  <div class="btn-group no-print">
    <?php if ($journal['status'] === 'draft'): ?>
      <?php if (user_can('journal.create')): ?><a class="btn ghost" href="<?= site_url('journals/' . $journal['id'] . '/edit') ?>"><?= lang('App.edit') ?></a><?php endif ?>
      <?php if (user_can('journal.post')): ?>
        <form method="post" action="<?= site_url('journals/' . $journal['id'] . '/post') ?>" onsubmit="return confirm('<?= esc(lang('Txn.post_confirm'), 'js') ?>')">
          <?= csrf_field() ?><button class="btn" type="submit"><?= lang('App.post') ?></button>
        </form>
      <?php endif ?>
      <?php if (user_can('journal.delete')): ?>
        <form method="post" action="<?= site_url('journals/' . $journal['id'] . '/delete') ?>" onsubmit="return confirm('<?= esc(lang('Txn.delete_draft_confirm'), 'js') ?>')">
          <?= csrf_field() ?><button class="btn danger" type="submit"><?= lang('App.delete') ?></button>
        </form>
      <?php endif ?>
    <?php endif ?>
    <button class="btn secondary" onclick="window.print()"><?= lang('App.print') ?></button>
    <a class="btn ghost" href="<?= site_url('journals') ?>"><?= lang('App.back') ?></a>
  </div>
</div>

<?php if ($journal['status'] === 'void'): ?>
  <div class="alert alert-error">
    <?= lang('Txn.voided_note', [esc($journal['voided_at']), esc($journal['void_reason'])]) ?>
    <?php if ($reversedBy): ?> · <?= lang('Txn.reversing_entry') ?> <a href="<?= site_url('journals/' . $reversedBy['id']) ?>"><?= esc($reversedBy['journal_no']) ?></a><?php endif ?>
  </div>
<?php endif ?>
<?php if ($reversal): ?>
  <div class="alert alert-success"><?= lang('Txn.is_reversing_for') ?>
    <a href="<?= site_url('journals/' . $reversal['id']) ?>"><?= esc($reversal['journal_no']) ?></a>.</div>
<?php endif ?>

<div class="card">
  <table class="grid tight">
    <tbody>
      <tr><td class="muted" style="width:130px"><?= lang('App.description') ?></td><td><?= esc($journal['description']) ?></td></tr>
      <tr><td class="muted"><?= lang('App.reference') ?></td><td><?= esc($journal['reference']) ?: '—' ?></td></tr>
      <tr><td class="muted"><?= lang('Txn.source') ?></td><td><?= esc(\App\Models\JournalModel::SOURCES[$journal['source']] ?? $journal['source']) ?></td></tr>
    </tbody>
  </table>
</div>

<div class="card">
  <table class="grid tight mono">
    <thead>
      <tr>
        <th><?= lang('App.account') ?></th><th style="font-family:sans-serif"><?= lang('App.memo') ?></th><th><?= lang('Txn.party') ?></th><th><?= lang('Txn.job') ?></th>
        <?php if ($isForeign): ?><th class="right"><?= lang('Txn.debit') ?> (<?= esc($journal['currency_code']) ?>)</th><th class="right"><?= lang('Txn.credit') ?></th><?php endif ?>
        <th class="right"><?= lang('Txn.debit') ?> (<?= base_code() ?>)</th><th class="right"><?= lang('Txn.credit') ?> (<?= base_code() ?>)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($lines as $l): ?>
        <tr>
          <td class="nowrap"><?= esc($l['account_code'] . ' · ' . $l['account_name']) ?></td>
          <td style="font-family:sans-serif"><?= esc($l['memo']) ?></td>
          <td style="font-family:sans-serif" class="small"><?= esc($l['customer_name'] ?? $l['supplier_name'] ?? '') ?></td>
          <td class="small"><?= esc($l['job_code'] ?? '') ?></td>
          <?php if ($isForeign): ?>
            <td class="right"><?= money($l['debit'], 2, true) ?></td>
            <td class="right"><?= money($l['credit'], 2, true) ?></td>
          <?php endif ?>
          <td class="right"><?= money($l['debit_base'], 2, true) ?></td>
          <td class="right"><?= money($l['credit_base'], 2, true) ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="<?= $isForeign ? 6 : 4 ?>" class="right"><?= lang('App.total') ?></td>
        <td class="right"><?= money($journal['total_debit']) ?></td>
        <td class="right"><?= money($journal['total_credit']) ?></td>
      </tr>
    </tfoot>
  </table>
</div>

<?php if ($journal['status'] === 'posted' && user_can('journal.void')): ?>
  <div class="card">
    <h2><?= lang('Txn.void_journal') ?></h2>
    <form method="post" action="<?= site_url('journals/' . $journal['id'] . '/void') ?>" class="inline"
      onsubmit="return confirm('<?= esc(lang('Txn.void_confirm'), 'js') ?>')">
      <?= csrf_field() ?>
      <input name="reason" placeholder="<?= esc(lang('Txn.void_reason'), 'attr') ?>" required style="max-width:360px">
      <button class="btn danger" type="submit"><?= lang('Txn.void_reverse') ?></button>
    </form>
  </div>
<?php endif ?>

<?= $this->endSection() ?>
