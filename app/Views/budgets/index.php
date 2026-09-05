<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1><?= lang('Nav.budgets') ?></h1></div>
  <div class="btn-group"><a class="btn" href="<?= site_url('budgets/new') ?>"><?= lang('Budget.new') ?></a></div>
</div>

<div class="card">
  <?php if (! $rows): ?>
    <p class="muted"><?= lang('Budget.empty') ?></p>
  <?php else: ?>
    <table class="grid tight">
      <thead><tr>
        <th><?= lang('Budget.f_name') ?></th>
        <th><?= lang('Budget.f_year') ?></th>
        <th class="right"><?= lang('Budget.lines') ?></th>
        <th class="right"><?= lang('App.total') ?> (<?= base_code() ?>)</th>
        <th></th>
      </tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <a href="<?= site_url('budgets/' . $r['id']) ?>"><strong><?= esc($r['name']) ?></strong></a>
              <?= $r['is_default'] ? ' <span class="badge badge-green">' . lang('Budget.default') . '</span>' : '' ?>
              <?php if (! empty($r['note'])): ?><div class="small muted"><?= esc($r['note']) ?></div><?php endif ?>
            </td>
            <td><?= (int) $r['year'] ?></td>
            <td class="right mono"><?= number_format($r['stats']['lines']) ?></td>
            <td class="right mono"><?= money($r['stats']['amount']) ?></td>
            <td class="right nowrap">
              <a class="btn sm ghost" href="<?= site_url('budgets/' . $r['id']) ?>"><?= lang('Budget.open') ?></a>
              <a class="btn sm ghost" href="<?= site_url('budgets/' . $r['id'] . '/edit') ?>"><?= lang('App.edit') ?></a>
              <?php if (! $r['is_default']): ?>
                <form method="post" action="<?= site_url('budgets/' . $r['id'] . '/set-default') ?>" class="inline">
                  <?= csrf_field() ?><button class="btn sm ghost" type="submit"><?= lang('Budget.make_default') ?></button>
                </form>
              <?php endif ?>
              <form method="post" action="<?= site_url('budgets/' . $r['id'] . '/delete') ?>" class="inline" onsubmit="return confirm('<?= esc(lang('Budget.confirm_delete'), 'js') ?>')">
                <?= csrf_field() ?><button class="btn sm ghost danger" type="submit"><?= lang('App.delete') ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
</div>

<p class="small muted"><?= lang('Budget.help') ?></p>

<?= $this->endSection() ?>
