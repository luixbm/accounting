<?php

/**
 * Modern shell — full-width sticky top bar with dropdown menus, roomier canvas.
 * `layout.php` includes it when design = modern. Nav data comes from
 * layouts/_nav.php, shared with the classic sidebar.
 */
$nav       = require __DIR__ . '/_nav.php';
$navFor    = $nav['navFor'];
$activeSeg = $nav['activeSeg'];
$logo      = company_logo_url();

/** One dropdown menu: a button + a panel of links, active-aware. */
$menu = static function (string $label, array $links, string $key) use ($navFor, $activeSeg) {
    $visible = array_filter($links, static fn ($l) => $l[4]);
    if (! $visible) {
        return;
    }
    $onSeg = in_array($activeSeg, array_column($visible, 0), true);
    echo '<div class="m-navitem' . ($onSeg ? ' on' : '') . '" data-menu="' . esc($key, 'attr') . '">';
    echo '<button type="button" class="m-navbtn">' . esc($label) . '<span class="m-caret" aria-hidden="true">&#9662;</span></button>';
    echo '<div class="m-menu">';
    foreach ($visible as [$seg, $url, $icon, $langKey]) {
        echo '<a class="m-menu-link ' . $navFor($seg) . '" href="' . site_url($url) . '">'
            . nav_icon($icon) . '<span>' . lang('Nav.' . $langKey) . '</span></a>';
    }
    echo '</div></div>';
};
?>
<div class="m-app" data-shell="modern">

  <header class="m-bar no-print">
    <a class="m-brand" href="<?= site_url($nav['dashboard'] ? 'dashboard' : 'journals') ?>">
      <?php if ($logo): ?>
        <img src="<?= esc($logo) ?>" alt="<?= esc(company_name()) ?>">
      <?php else: ?>
        <span class="m-brand-mark"><?= esc(company_initials()) ?></span>
      <?php endif ?>
      <span class="m-brand-name"><?= esc(company_name()) ?></span>
    </a>

    <button type="button" class="m-burger" id="mBurger" aria-label="Menu" aria-expanded="false">&#9776;</button>

    <nav class="m-nav" id="mNav">
      <?php if ($nav['dashboard']): ?>
        <a class="m-navlink <?= $navFor('dashboard') ?>" href="<?= site_url('dashboard') ?>"><?= lang('Nav.dashboard') ?></a>
      <?php endif ?>
      <?php $menu(lang('Nav.group_master'), $nav['master'], 'master') ?>
      <?php $menu(lang('Nav.group_txn'), $nav['txn'], 'txn') ?>
      <?php if ($nav['reports']): ?>
        <a class="m-navlink <?= $navFor('reports') ?>" href="<?= site_url('reports') ?>"><?= lang('Nav.reports') ?></a>
      <?php endif ?>
      <?php $menu(lang('Nav.group_setup'), $nav['setup'], 'setup') ?>
    </nav>

    <div class="m-right">
      <?php if (count($nav['companies']) > 1): ?>
        <form method="post" action="<?= site_url('companies/switch') ?>" class="m-company">
          <?= csrf_field() ?>
          <input type="hidden" name="return" value="<?= esc(current_url()) ?>">
          <select name="company_id" onchange="this.form.submit()" aria-label="<?= esc(lang('Nav.companies'), 'attr') ?>">
            <?php foreach ($nav['companies'] as $c): ?>
              <option value="<?= $c['id'] ?>" <?= (int) $c['id'] === active_company_id() ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
            <?php endforeach ?>
          </select>
        </form>
      <?php endif ?>

      <div class="theme-picker" id="themePicker" title="<?= lang('Nav.theme') ?>">
        <button type="button" class="sw-light" data-theme="light" aria-label="Modern light"></button>
        <button type="button" class="sw-green" data-theme="green" aria-label="Green"></button>
        <button type="button" class="sw-blue" data-theme="blue" aria-label="Blue"></button>
        <button type="button" class="sw-dark" data-theme="dark" aria-label="Dark"></button>
      </div>

      <div class="lang-picker" title="<?= lang('Nav.language') ?>">
        <a href="<?= site_url('lang/id') ?>" class="<?= app_locale() === 'id' ? 'on' : '' ?>">ID</a>
        <a href="<?= site_url('lang/en') ?>" class="<?= app_locale() === 'en' ? 'on' : '' ?>">EN</a>
      </div>

      <div class="m-navitem m-user" data-menu="user">
        <button type="button" class="m-navbtn"><?= user_avatar_tag(null, 'avatar-sm') ?><span class="m-caret" aria-hidden="true">&#9662;</span></button>
        <div class="m-menu m-menu-right">
          <a class="m-menu-link" href="<?= site_url('profile') ?>"><?= nav_icon('users') ?><span><?= lang('Nav.profile') ?></span></a>
          <a class="m-menu-link" href="<?= site_url('logout') ?>"><span><?= lang('Nav.logout') ?></span></a>
        </div>
      </div>
    </div>
  </header>

  <div class="content">
    <main class="wrap">
      <?php include __DIR__ . '/_page_chrome.php'; ?>
      <?= $this->renderSection('content') ?>
    </main>
  </div>
</div>

<script>
  (function () {
    var nav = document.getElementById('mNav'),
        burger = document.getElementById('mBurger');

    // mobile: hamburger shows / hides the whole nav
    if (burger && nav) {
      burger.addEventListener('click', function () {
        var open = nav.classList.toggle('open');
        burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }

    // dropdown menus: click to toggle, click-outside / Esc to close
    var items = [].slice.call(document.querySelectorAll('.m-navitem'));
    function closeAll(except) {
      items.forEach(function (it) { if (it !== except) it.classList.remove('open'); });
    }
    items.forEach(function (it) {
      var btn = it.querySelector('.m-navbtn');
      if (!btn) return;
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var willOpen = !it.classList.contains('open');
        closeAll(it);
        it.classList.toggle('open', willOpen);
      });
    });
    document.addEventListener('click', function () { closeAll(null); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(null); });
  })();
</script>
