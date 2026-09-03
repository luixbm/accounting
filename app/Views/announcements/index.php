<?= $this->extend('layout') ?>
<?= $this->section('content') ?>

<div class="page-head">
  <div><h1><?= lang('Nav.announcements') ?></h1></div>
  <div class="btn-group"><a class="btn" href="<?= site_url('announcements/new') ?>"><?= lang('Announce.new') ?></a></div>
</div>

<div class="card">
  <?php if (! $rows): ?>
    <p class="muted"><?= lang('Announce.empty') ?></p>
  <?php else: ?>
    <table class="grid tight">
      <thead><tr>
        <th><?= lang('Announce.f_title') ?></th>
        <th><?= lang('Announce.f_level') ?></th>
        <th><?= lang('Announce.f_window') ?></th>
        <th><?= lang('Announce.f_pinned') ?></th>
        <th><?= lang('App.status') ?></th>
        <th></th>
      </tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr<?= $r['is_active'] ? '' : ' class="muted"' ?>>
            <td>
              <strong><?= esc($r['title']) ?></strong>
              <?php if (! empty($r['body'])): ?><div class="small muted"><?= esc(mb_strimwidth((string) $r['body'], 0, 90, '…')) ?></div><?php endif ?>
            </td>
            <td><span class="badge badge-<?= $r['level'] === 'warning' ? 'amber' : ($r['level'] === 'success' ? 'green' : 'gray') ?>"><?= esc($r['level']) ?></span></td>
            <td class="small muted">
              <?= $r['starts_on'] ? esc(date_id($r['starts_on'])) : '—' ?> → <?= $r['ends_on'] ? esc(date_id($r['ends_on'])) : '—' ?>
            </td>
            <td><?= $r['pinned'] ? '<span class="badge badge-amber">pinned</span>' : '' ?></td>
            <td><?= $r['is_active'] ? '<span class="badge badge-green">active</span>' : '<span class="badge badge-gray">hidden</span>' ?></td>
            <td class="right nowrap">
              <a class="btn sm ghost" href="<?= site_url('announcements/' . $r['id'] . '/edit') ?>"><?= lang('App.edit') ?></a>
              <form method="post" action="<?= site_url('announcements/' . $r['id'] . '/toggle') ?>" class="inline">
                <?= csrf_field() ?>
                <button class="btn sm ghost" type="submit"><?= $r['is_active'] ? lang('Announce.hide') : lang('Announce.show') ?></button>
              </form>
              <form method="post" action="<?= site_url('announcements/' . $r['id'] . '/delete') ?>" class="inline" onsubmit="return confirm('<?= lang('Announce.confirm_delete') ?>')">
                <?= csrf_field() ?>
                <button class="btn sm ghost danger" type="submit"><?= lang('App.delete') ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  <?php endif ?>
</div>

<p class="small muted"><?= lang('Announce.help') ?></p>

<?= $this->endSection() ?>
