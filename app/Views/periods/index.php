<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1><?= lang('Setup.periods_h', [$year]) ?></h1>
    <div class="muted small"><?= lang('Setup.periods_note') ?></div>
  </div>
  <div class="btn-group">
    <a class="btn ghost" href="<?= site_url('periods/' . ($year - 1)) ?>">&laquo; <?= $year - 1 ?></a>
    <a class="btn ghost" href="<?= site_url('periods/' . ($year + 1)) ?>"><?= $year + 1 ?> &raquo;</a>
  </div>
</div>

<div class="card">
  <table class="grid tight">
    <thead><tr><th><?= lang('Setup.month') ?></th><th class="center"><?= lang('Nav.journals') ?></th><th><?= lang('App.status') ?></th><th class="right"></th></tr></thead>
    <tbody>
      <?php foreach ($months as $m => $info): ?>
        <tr>
          <td><?= esc($info['label']) ?></td>
          <td class="center"><?= $info['count'] ?></td>
          <td><?= status_badge($info['status']) ?></td>
          <td class="right">
            <?php if ($canClose): ?>
              <?php if ($info['status'] === 'open'): ?>
                <form method="post" action="<?= site_url('periods/close') ?>" style="display:inline"
                  onsubmit="return confirm('<?= esc(lang('Setup.close_month_confirm', [esc($info['label']), $year]), 'js') ?>')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="year" value="<?= $year ?>"><input type="hidden" name="month" value="<?= $m ?>">
                  <button class="btn sm ghost" type="submit"><?= lang('App.close') ?></button>
                </form>
              <?php else: ?>
                <form method="post" action="<?= site_url('periods/reopen') ?>" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="year" value="<?= $year ?>"><input type="hidden" name="month" value="<?= $m ?>">
                  <button class="btn sm ghost" type="submit"><?= lang('App.reopen') ?></button>
                </form>
              <?php endif ?>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
