<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<?php $today = date('Y-m-d'); ?>

<div class="page-head">
  <div><h1><?= lang('Nav.recurring_journals') ?></h1><div class="muted small"><?= lang('Recurring.sub') ?></div></div>
  <div class="btn-group"><a class="btn" href="<?= site_url('journals/recurring/new') ?>"><?= lang('Recurring.new') ?></a></div>
</div>

<div class="card">
  <?php if (! $rows): ?>
    <p class="muted"><?= lang('Recurring.empty') ?></p>
  <?php else: ?>
    <table class="grid tight">
      <thead><tr>
        <th><?= lang('Recurring.f_name') ?></th>
        <th><?= lang('Recurring.f_frequency') ?></th>
        <th><?= lang('Recurring.f_next_date') ?></th>
        <th class="right"><?= lang('Txn.lines') ?></th>
        <th class="right"><?= lang('App.total') ?></th>
        <th></th>
      </tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr<?= $r['is_active'] ? '' : ' class="muted"' ?>>
            <td>
              <a href="<?= site_url('journals/recurring/' . $r['id']) ?>"><strong><?= esc($r['name']) ?></strong></a>
              <?= $r['is_active'] ? '' : ' <span class="badge badge-gray">' . lang('Recurring.inactive') . '</span>' ?>
              <div class="small muted"><?= esc($r['description']) ?></div>
            </td>
            <td><?= lang('Recurring.freq_' . $r['frequency']) ?></td>
            <td class="nowrap">
              <?= $r['next_date'] ? date_id($r['next_date']) : '<span class="muted">—</span>' ?>
              <?php if ($r['due']): ?> <span class="badge badge-red"><?= lang('Recurring.due') ?></span><?php endif ?>
            </td>
            <td class="right mono"><?= (int) $r['nlines'] ?></td>
            <td class="right mono"><?= money($r['net']) ?></td>
            <td class="right nowrap">
              <form method="post" action="<?= site_url('journals/recurring/' . $r['id'] . '/generate') ?>" class="inline">
                <?= csrf_field() ?><button class="btn sm" type="submit"<?= $r['nlines'] ? '' : ' disabled' ?>><?= lang('Recurring.generate') ?></button>
              </form>
              <a class="btn sm ghost" href="<?= site_url('journals/recurring/' . $r['id'] . '/edit') ?>"><?= lang('App.edit') ?></a>
              <form method="post" action="<?= site_url('journals/recurring/' . $r['id'] . '/toggle') ?>" class="inline">
                <?= csrf_field() ?><button class="btn sm ghost" type="submit"><?= $r['is_active'] ? lang('Recurring.deactivate') : lang('Recurring.activate') ?></button>
              </form>
              <form method="post" action="<?= site_url('journals/recurring/' . $r['id'] . '/delete') ?>" class="inline"
                onsubmit="return confirm('<?= esc(lang('Recurring.confirm_delete'), 'js') ?>')">
                <?= csrf_field() ?><button class="btn sm ghost danger" type="submit"><?= lang('App.delete') ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
</div>

<p class="small muted"><?= lang('Recurring.help') ?></p>

<?= $this->endSection() ?>
