<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head"><h1><?= esc($title) ?></h1></div>

<div class="card" style="max-width:520px">
  <form method="post" action="<?= site_url('budgets/' . $ver['id'] . '/row/' . $acc['id']) ?>">
    <?= csrf_field() ?>
    <p class="muted small"><?= lang('Budget.row_note') ?></p>
    <table class="grid tight"><tbody>
      <?php for ($m = 1; $m <= 12; $m++): ?>
        <tr>
          <td class="muted" style="width:120px"><?= date('F', mktime(0, 0, 0, $m, 1)) ?></td>
          <td><input class="mono" name="m[<?= $m ?>]" value="<?= old('m.' . $m, isset($months[$m]) ? (float) $months[$m] : '') ?>" inputmode="decimal" style="text-align:right"></td>
        </tr>
      <?php endfor ?>
    </tbody></table>
    <div class="btn-group" style="margin-top:14px">
      <button class="btn" type="submit"><?= lang('App.save') ?></button>
      <a class="btn ghost" href="<?= site_url('budgets/' . $ver['id']) ?>"><?= lang('App.cancel') ?></a>
    </div>
  </form>
</div>

<?= $this->endSection() ?>
