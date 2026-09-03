<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1><?= lang('Setup.companies_h') ?></h1><div class="muted small"><?= lang('Setup.companies_note') ?></div></div>
  <div class="btn-group"><a class="btn" href="<?= site_url('companies/new') ?>"><?= lang('Setup.new_company') ?></a></div>
</div>

<div class="card">
  <table class="grid tight">
    <thead><tr><th><?= lang('Report.col_code') ?></th><th><?= lang('Report.col_name') ?></th><th>NPWP</th><th><?= lang('Setup.col_base') ?></th><th><?= lang('App.status') ?></th><th></th></tr></thead>
    <tbody>
      <?php foreach ($companies as $c): ?>
        <tr>
          <td class="mono"><?= esc($c['code']) ?><?= (int) $c['id'] === active_company_id() ? ' <span class="badge badge-green">' . esc(lang('App.active')) . '</span>' : '' ?></td>
          <td><?= esc($c['name']) ?></td>
          <td class="small mono"><?= esc($c['npwp']) ?></td>
          <td class="small"><?= esc($c['base_currency']) ?></td>
          <td><?= $c['is_active'] ? esc(lang('App.active')) : '<span class="muted">' . esc(lang('App.inactive')) . '</span>' ?></td>
          <td class="right nowrap">
            <?php if ((int) $c['id'] !== active_company_id() && $c['is_active']): ?>
              <form method="post" action="<?= site_url('companies/switch') ?>" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="company_id" value="<?= $c['id'] ?>">
                <input type="hidden" name="return" value="<?= site_url('companies') ?>">
                <button class="btn sm ghost" type="submit"><?= lang('Setup.switch_to') ?></button>
              </form>
            <?php endif ?>
            <a class="btn sm ghost" href="<?= site_url('companies/' . $c['id'] . '/edit') ?>"><?= lang('App.edit') ?></a>
          </td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>

<?= $this->endSection() ?>
