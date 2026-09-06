<?php

/**
 * Public sign-in page. Self-contained (no layout) so the look is fully under
 * our control. Appearance is driven by Setup -> Login Page (Config\LoginPage
 * + setting('LoginPage.*'), edited by LoginPageController).
 */
$cfg     = new \Config\LoginPage();
$L       = static fn (string $k): string => (string) (setting('LoginPage.' . $k) ?? '');
$val     = static fn (string $k) => $L($k) !== '' ? $L($k) : ($cfg->{$k} ?? '');

$style   = in_array($L('style'), \Config\LoginPage::STYLES, true) ? $L('style') : 'luxury';
$event   = in_array($L('event'), \Config\LoginPage::EVENTS, true) ? $L('event') : 'default';
$anim    = in_array($L('animation'), \Config\LoginPage::ANIMATIONS, true) ? $L('animation') : 'full';
$preset  = \Config\LoginPage::PRESETS[$event] ?? \Config\LoginPage::PRESETS['default'];
$accent  = preg_match('/^#[0-9a-fA-F]{6}$/', $L('accent')) ? $L('accent') : $preset['accent'];

$brand   = $val('brand') !== '' ? $val('brand') : company_name();
$mark     = (string) $val('mark');
$mark     = $mark !== '' ? mb_substr($mark, 0, 2) : strtoupper(mb_substr(preg_replace('/[^A-Za-z0-9]/', '', $brand) ?: 'AC', 0, 2));
$headline = (string) $val('headline');
$subtitle = (string) $val('subtitle');
$tagline  = (string) $val('tagline');
$footer   = $val('footer') !== '' ? $val('footer') : ('© ' . date('Y') . ' ' . $brand . '  •  Multi-Currency Engine v4.2');

$bg = $L('bgImage') !== '' && is_file(FCPATH . $L('bgImage'))
    ? "radial-gradient(circle at 30% 45%, rgba(10,17,24,.55) 0%, rgba(10,17,24,.96) 100%), url('" . base_url($L('bgImage')) . "') center/cover no-repeat"
    : $preset['gradient'];

$anns   = model(\App\Models\AnnouncementModel::class)->forLogin();
$locale = function_exists('app_locale') ? app_locale() : 'id';
$errs   = session('errors');
$acc2   = preg_match('/^#[0-9a-fA-F]{6}$/', $accent) ? $accent : '#1f5f8b';
?>
<?php if ($style === 'simple'): ?>
<!DOCTYPE html>
<html lang="<?= esc($locale) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc(lang('Auth.login')) ?> · <?= esc($brand) ?></title>
<style>
  :root{--accent:<?= $acc2 ?>;--ground:#F5F6F3;--card:#fff;--ink:#1f2733;--muted:#6b7686;--line:#e2e5ea}
  *{box-sizing:border-box;margin:0;padding:0;font-family:system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif}
  html,body{min-height:100%}
  body{background:var(--ground);color:var(--ink);display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;padding:28px 16px;gap:14px}
  .wrap{width:100%;max-width:400px;display:flex;flex-direction:column;gap:12px}
  .brand{display:flex;align-items:center;gap:10px;justify-content:center;margin-bottom:2px}
  .brand-mark{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;
    font-weight:800;font-size:.95rem;color:#fff;background:var(--accent);letter-spacing:.5px}
  .brand-name{font-weight:700;font-size:1.05rem;letter-spacing:1.5px;color:var(--ink)}
  .card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:30px 28px;box-shadow:0 1px 2px rgba(31,39,51,.05),0 12px 30px -18px rgba(31,39,51,.2)}
  .card h1{font-size:1.2rem;margin-bottom:4px}
  .card .sub{font-size:.86rem;color:var(--muted);margin-bottom:20px}
  .field{margin-bottom:15px}
  .field label{display:block;font-size:.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
  .field input[type=email],.field input[type=password]{width:100%;padding:11px 13px;border:1px solid var(--line);border-radius:9px;font-size:.95rem;color:var(--ink);background:#fff;outline:none}
  .field input:focus{border-color:var(--accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--accent) 18%,transparent)}
  .row{display:flex;justify-content:space-between;align-items:center;font-size:.83rem;gap:10px;flex-wrap:wrap;margin-bottom:18px}
  .row a{color:var(--accent);text-decoration:none}
  .remember{display:flex;align-items:center;gap:7px;color:var(--muted);cursor:pointer}
  .remember input{accent-color:var(--accent)}
  .btn{width:100%;padding:12px;border:none;border-radius:9px;background:var(--accent);color:#fff;font-weight:700;font-size:.95rem;letter-spacing:.5px;cursor:pointer}
  .btn:hover{filter:brightness(1.06)}
  .need{text-align:center;font-size:.84rem;color:var(--muted);margin-top:14px}
  .need a{color:var(--accent);text-decoration:none}
  .alert{border-radius:9px;padding:10px 13px;font-size:.85rem;margin-bottom:14px;border:1px solid transparent}
  .alert-error{background:#fbe9e7;border-color:#f0c4bf;color:#b3261e}
  .alert-success{background:#e6f4ec;border-color:#bfe3cd;color:#1a7f45}
  .banner{width:100%;max-width:400px;border-radius:10px;padding:10px 13px;font-size:.84rem;border:1px solid var(--line);background:#fff}
  .banner.warning{background:#fef3c7;border-color:#fcd97a}
  .banner.success{background:#e6f4ec;border-color:#bfe3cd}
  .banner b{font-weight:700}
  .langsw{font-size:.75rem;color:var(--muted)}
  .langsw a{color:var(--muted);text-decoration:none;padding:0 5px}
  .langsw a.on{color:var(--accent);font-weight:700}
</style>
</head>
<body>
  <?php foreach ($anns as $a): ?>
    <?php $lvl = in_array($a['level'], ['info', 'warning', 'success'], true) ? $a['level'] : 'info'; ?>
    <div class="banner <?= $lvl ?>"><b><?= esc($a['title']) ?></b><?php if (! empty($a['body'])): ?> · <?= esc($a['body']) ?><?php endif ?></div>
  <?php endforeach ?>

  <div class="wrap">
    <div class="brand">
      <div class="brand-mark"><?= esc($mark) ?></div>
      <div class="brand-name"><?= esc(strtoupper($brand)) ?></div>
    </div>

    <div class="card">
      <h1><?= esc(lang('Auth.login')) ?></h1>
      <?php if ($subtitle !== ''): ?><div class="sub"><?= esc($subtitle) ?></div><?php endif ?>

      <?php if (session('error') !== null): ?>
        <div class="alert alert-error"><?= esc(session('error')) ?></div>
      <?php elseif ($errs !== null): ?>
        <div class="alert alert-error">
          <?php if (is_array($errs)): ?><?php foreach ($errs as $e): ?><?= esc($e) ?><br><?php endforeach ?>
          <?php else: ?><?= esc($errs) ?><?php endif ?>
        </div>
      <?php endif ?>
      <?php if (session('message') !== null): ?>
        <div class="alert alert-success"><?= esc(session('message')) ?></div>
      <?php endif ?>

      <form action="<?= url_to('login') ?>" method="post">
        <?= csrf_field() ?>
        <div class="field">
          <label for="email"><?= esc(lang('Auth.email')) ?></label>
          <input type="email" id="email" name="email" inputmode="email" autocomplete="email" value="<?= esc(old('email')) ?>" required>
        </div>
        <div class="field">
          <label for="password"><?= esc(lang('Auth.password')) ?></label>
          <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <div class="row">
          <?php if (setting('Auth.sessionConfig')['allowRemembering']): ?>
            <label class="remember"><input type="checkbox" name="remember" <?= old('remember') ? 'checked' : '' ?>> <?= esc(lang('Auth.rememberMe')) ?></label>
          <?php else: ?><span></span><?php endif ?>
          <?php if (setting('Auth.allowMagicLinkLogins')): ?><a href="<?= url_to('magic-link') ?>"><?= esc(lang('Auth.useMagicLink')) ?></a><?php endif ?>
        </div>
        <button type="submit" class="btn"><?= esc(lang('Auth.login')) ?></button>
        <?php if (setting('Auth.allowRegistration')): ?>
          <p class="need"><?= esc(lang('Auth.needAccount')) ?> <a href="<?= url_to('register') ?>"><?= esc(lang('Auth.register')) ?></a></p>
        <?php endif ?>
      </form>
    </div>

    <div class="langsw" style="text-align:center">
      <a href="<?= site_url('lang/id') ?>" class="<?= $locale === 'id' ? 'on' : '' ?>">ID</a>
      <a href="<?= site_url('lang/en') ?>" class="<?= $locale === 'en' ? 'on' : '' ?>">EN</a>
    </div>
  </div>
</body>
</html>
<?php else: ?>
<!DOCTYPE html>
<html lang="<?= esc($locale) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc(lang('Auth.login')) ?> · <?= esc($brand) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --gold:#D4AF37; --gold-light:#F3E5AB;
    --navy:#0A1118; --card:rgba(16,26,38,.75);
    --text:#E2E8F0; --muted:#94A3B8;
    --accent:<?= $accent ?>;
  }
  *{box-sizing:border-box;margin:0;padding:0;font-family:'Plus Jakarta Sans',system-ui,-apple-system,Segoe UI,sans-serif}
  html,body{height:100%;background:var(--navy);color:var(--text);overflow:hidden}

  .bg{position:fixed;inset:0;z-index:1}
  .bg-img{position:absolute;inset:0;background:<?= $bg ?>;filter:brightness(.72) contrast(1.08);transition:background-image .8s}
  canvas#loginfx{position:absolute;inset:0;z-index:2;pointer-events:none}

  .app{position:relative;z-index:10;display:flex;height:100vh;width:100vw}
  .hero{flex:1.2;display:flex;flex-direction:column;justify-content:space-between;padding:56px clamp(40px,6vw,80px)}
  .brand{display:flex;align-items:center;gap:14px}
  .brand-mark{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;
    font-family:'Cinzel',serif;font-weight:700;font-size:1.35rem;color:var(--navy);
    background:linear-gradient(135deg,var(--accent),var(--gold-light));box-shadow:0 0 22px color-mix(in srgb,var(--accent) 45%,transparent)}
  .brand-name{font-family:'Cinzel',serif;font-size:1.55rem;font-weight:700;letter-spacing:2px;
    background:linear-gradient(180deg,#fff 0%,var(--accent) 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
  .brand-tag{font-size:.72rem;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);margin-top:2px}

  .hero h1{font-family:'Cinzel',serif;font-size:clamp(2rem,3.4vw,3rem);line-height:1.18;margin-bottom:18px;text-shadow:0 10px 30px rgba(0,0,0,.8)}
  .hero h1 .hl{color:var(--accent)}
  .hero-foot{display:flex;gap:26px;font-size:.82rem;color:var(--muted);flex-wrap:wrap}

  .login{flex:.8;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;padding:32px}
  .card{width:100%;max-width:432px;background:var(--card);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
    border:1px solid color-mix(in srgb,var(--accent) 25%,transparent);border-radius:22px;padding:40px 36px;box-shadow:0 30px 60px rgba(0,0,0,.55)}
  .card h2{font-family:'Cinzel',serif;font-size:1.6rem;color:#fff;margin-bottom:6px}
  .card .sub{font-size:.88rem;color:var(--muted);margin-bottom:26px}

  .field{margin-bottom:18px}
  .field label{display:block;font-size:.72rem;text-transform:uppercase;letter-spacing:1px;color:var(--gold-light);margin-bottom:7px}
  .field input[type=email],.field input[type=password]{width:100%;padding:13px 16px;background:rgba(255,255,255,.04);
    border:1px solid rgba(255,255,255,.15);border-radius:11px;color:#fff;font-size:.95rem;outline:none;transition:.25s}
  .field input:focus{border-color:var(--accent);box-shadow:0 0 14px color-mix(in srgb,var(--accent) 25%,transparent);background:rgba(255,255,255,.08)}
  .row{display:flex;justify-content:space-between;align-items:center;font-size:.83rem;margin-bottom:22px;gap:10px;flex-wrap:wrap}
  .row a{color:var(--accent);text-decoration:none}
  .row a:hover{color:var(--gold-light)}
  .remember{display:flex;align-items:center;gap:8px;color:var(--muted);cursor:pointer}
  .remember input{accent-color:var(--accent)}

  .btn{width:100%;padding:14px;border:none;border-radius:11px;color:var(--navy);font-weight:700;font-size:.98rem;
    letter-spacing:1px;cursor:pointer;background:linear-gradient(135deg,var(--accent) 0%,#B8860B 100%);
    box-shadow:0 10px 24px color-mix(in srgb,var(--accent) 30%,transparent);transition:.25s}
  .btn:hover{transform:translateY(-2px);box-shadow:0 15px 30px color-mix(in srgb,var(--accent) 45%,transparent)}

  .alert{border-radius:11px;padding:11px 14px;font-size:.85rem;margin-bottom:16px;border:1px solid transparent}
  .alert-error{background:rgba(185,28,28,.18);border-color:rgba(239,68,68,.4);color:#fecaca}
  .alert-success{background:rgba(21,128,61,.18);border-color:rgba(74,222,128,.4);color:#bbf7d0}
  .alert-info{background:rgba(37,99,235,.16);border-color:rgba(96,165,250,.4);color:#bfdbfe}
  .alert-warning{background:rgba(180,83,9,.18);border-color:rgba(251,191,36,.4);color:#fde68a}

  .banner-stack{position:fixed;top:0;left:0;right:0;z-index:100;display:flex;flex-direction:column}
  .banner{padding:9px 20px;text-align:center;font-size:.82rem;font-weight:600;letter-spacing:.3px;box-shadow:0 4px 14px rgba(0,0,0,.4)}
  .banner.info{background:linear-gradient(90deg,#1d4ed8,#1e40af);color:#fff}
  .banner.warning{background:linear-gradient(90deg,#b45309,#92400e);color:#fff}
  .banner.success{background:linear-gradient(90deg,#15803d,#166534);color:#fff}
  .banner b{font-weight:700}
  .banner span{font-weight:400;opacity:.92}

  .langsw{align-self:center;font-size:.75rem;color:var(--muted);
    background:rgba(10,17,24,.7);border:1px solid color-mix(in srgb,var(--accent) 30%,transparent);border-radius:10px;padding:6px 12px}
  .langsw a{color:var(--muted);text-decoration:none;padding:0 5px}
  .langsw a.on{color:var(--accent);font-weight:700}

  @media (max-width:1024px){ .hero{display:none} .login{flex:1} }
  @media (prefers-reduced-motion:reduce){ .btn{transition:none} }
</style>
</head>
<body>

<?php if ($anns): ?>
  <div class="banner-stack">
    <?php foreach ($anns as $a): ?>
      <?php $lvl = in_array($a['level'], ['info', 'warning', 'success'], true) ? $a['level'] : 'info'; ?>
      <div class="banner <?= $lvl ?>">
        <b><?= esc($a['title']) ?></b><?php if (! empty($a['body'])): ?> <span>· <?= esc($a['body']) ?></span><?php endif ?>
      </div>
    <?php endforeach ?>
  </div>
<?php endif ?>

<div class="bg">
  <div class="bg-img"></div>
  <?php if ($anim !== 'off'): ?><canvas id="loginfx"></canvas><?php endif ?>
</div>

<div class="app">
  <div class="hero">
    <div class="brand">
      <div class="brand-mark"><?= esc($mark) ?></div>
      <div>
        <div class="brand-name"><?= esc(strtoupper($brand)) ?></div>
        <?php if ($tagline !== ''): ?><div class="brand-tag"><?= esc($tagline) ?></div><?php endif ?>
      </div>
    </div>

    <div>
      <h1><?php
        $parts = explode("\n", $headline);
      foreach ($parts as $i => $line) {
          echo ($i === 0 ? '' : '<br>') . ($i === count($parts) - 1 && count($parts) > 1
              ? '<span class="hl">' . esc($line) . '</span>'
              : esc($line));
      }
      ?></h1>
    </div>

    <div class="hero-foot"><span><?= esc($footer) ?></span></div>
  </div>

  <div class="login">
    <div class="card">
      <h2><?= esc(lang('Auth.login')) ?></h2>
      <div class="sub"><?= esc($subtitle !== '' ? $subtitle : $brand) ?></div>

      <?php if (session('error') !== null): ?>
        <div class="alert alert-error"><?= esc(session('error')) ?></div>
      <?php elseif ($errs !== null): ?>
        <div class="alert alert-error">
          <?php if (is_array($errs)): ?>
            <?php foreach ($errs as $e): ?><?= esc($e) ?><br><?php endforeach ?>
          <?php else: ?><?= esc($errs) ?><?php endif ?>
        </div>
      <?php endif ?>
      <?php if (session('message') !== null): ?>
        <div class="alert alert-success"><?= esc(session('message')) ?></div>
      <?php endif ?>

      <form action="<?= url_to('login') ?>" method="post">
        <?= csrf_field() ?>

        <div class="field">
          <label for="email"><?= esc(lang('Auth.email')) ?></label>
          <input type="email" id="email" name="email" inputmode="email" autocomplete="email"
            value="<?= esc(old('email')) ?>" placeholder="nama@perusahaan.com" required>
        </div>

        <div class="field">
          <label for="password"><?= esc(lang('Auth.password')) ?></label>
          <input type="password" id="password" name="password" autocomplete="current-password"
            placeholder="••••••••••" required>
        </div>

        <div class="row">
          <?php if (setting('Auth.sessionConfig')['allowRemembering']): ?>
            <label class="remember">
              <input type="checkbox" name="remember" <?= old('remember') ? 'checked' : '' ?>>
              <?= esc(lang('Auth.rememberMe')) ?>
            </label>
          <?php else: ?><span></span><?php endif ?>
          <?php if (setting('Auth.allowMagicLinkLogins')): ?>
            <a href="<?= url_to('magic-link') ?>"><?= esc(lang('Auth.useMagicLink')) ?></a>
          <?php endif ?>
        </div>

        <button type="submit" class="btn"><?= esc(strtoupper(lang('Auth.login'))) ?></button>

        <?php if (setting('Auth.allowRegistration')): ?>
          <p class="row" style="justify-content:center;margin:18px 0 0">
            <?= esc(lang('Auth.needAccount')) ?> <a href="<?= url_to('register') ?>"><?= esc(lang('Auth.register')) ?></a>
          </p>
        <?php endif ?>
      </form>
    </div>

    <div class="langsw" title="Language">
      <a href="<?= site_url('lang/id') ?>" class="<?= $locale === 'id' ? 'on' : '' ?>">ID</a>
      <a href="<?= site_url('lang/en') ?>" class="<?= $locale === 'en' ? 'on' : '' ?>">EN</a>
    </div>
  </div>
</div>

<?php if ($anim !== 'off'): ?>
<script>
(function () {
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var canvas = document.getElementById('loginfx');
  if (!canvas) return;
  var ctx = canvas.getContext('2d');
  var ACCENT = '<?= $accent ?>';
  var MODE = '<?= $anim ?>';

  function fit() { canvas.width = innerWidth; canvas.height = innerHeight; }
  addEventListener('resize', fit); fit();

  var glyphs = ['$', '€', '¥', '£', 'Rp', 'RM', 'HK$'];
  function hexA(hex, a) {
    var n = parseInt(hex.slice(1), 16);
    return 'rgba(' + (n >> 16 & 255) + ',' + (n >> 8 & 255) + ',' + (n & 255) + ',' + a + ')';
  }

  var floaters = [];
  var count = MODE === 'full' ? 25 : 13;
  for (var i = 0; i < count; i++) {
    floaters.push({
      t: glyphs[(Math.random() * glyphs.length) | 0],
      x: Math.random() * innerWidth, y: Math.random() * innerHeight,
      s: 0.25 + Math.random() * (MODE === 'full' ? 0.7 : 0.35),
      o: 0.15 + Math.random() * 0.45, sz: 13 + Math.random() * (MODE === 'full' ? 18 : 12)
    });
  }

  // hubs + flight routes (full mode only)
  var hubs = [
    { x: 0.78, y: 0.68 }, { x: 0.76, y: 0.67 }, { x: 0.73, y: 0.63 }, { x: 0.76, y: 0.62 },
    { x: 0.74, y: 0.61 }, { x: 0.80, y: 0.52 }, { x: 0.50, y: 0.35 }, { x: 0.22, y: 0.40 }
  ];
  var routes = [
    { f: 0, t: 4, p: 0, v: 0.005 }, { f: 4, t: 5, p: 0.3, v: 0.004 }, { f: 5, t: 6, p: 0.6, v: 0.003 },
    { f: 6, t: 7, p: 0.2, v: 0.002 }, { f: 1, t: 0, p: 0.5, v: 0.006 }, { f: 2, t: 3, p: 0.1, v: 0.005 }
  ];

  function frame() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    floaters.forEach(function (c) {
      ctx.fillStyle = hexA(ACCENT, c.o);
      ctx.font = c.sz + "px 'Plus Jakarta Sans', sans-serif";
      ctx.fillText(c.t, c.x, c.y);
      c.y -= c.s; if (c.y < -20) c.y = canvas.height + 20;
    });

    if (MODE === 'full') {
      hubs.forEach(function (h) {
        var hx = h.x * canvas.width, hy = h.y * canvas.height;
        ctx.beginPath(); ctx.arc(hx, hy, 4, 0, 7); ctx.fillStyle = ACCENT;
        ctx.shadowBlur = 10; ctx.shadowColor = ACCENT; ctx.fill(); ctx.shadowBlur = 0;
        ctx.beginPath(); ctx.arc(hx, hy, 12, 0, 7); ctx.strokeStyle = hexA(ACCENT, 0.2); ctx.stroke();
      });
      routes.forEach(function (r) {
        var s = hubs[r.f], e = hubs[r.t];
        var sx = s.x * canvas.width, sy = s.y * canvas.height, ex = e.x * canvas.width, ey = e.y * canvas.height;
        var cx = (sx + ex) / 2, cy = (sy + ey) / 2 - 80;
        ctx.beginPath(); ctx.moveTo(sx, sy); ctx.quadraticCurveTo(cx, cy, ex, ey);
        ctx.strokeStyle = hexA(ACCENT, 0.15); ctx.lineWidth = 1; ctx.setLineDash([4, 4]); ctx.stroke(); ctx.setLineDash([]);
        if (!reduce) { r.p += r.v; if (r.p > 1) r.p = 0; }
        var t = r.p, px = (1 - t) * (1 - t) * sx + 2 * (1 - t) * t * cx + t * t * ex,
            py = (1 - t) * (1 - t) * sy + 2 * (1 - t) * t * cy + t * t * ey;
        ctx.beginPath(); ctx.arc(px, py, 3, 0, 7); ctx.fillStyle = '#fff';
        ctx.shadowBlur = 12; ctx.shadowColor = '#fff'; ctx.fill(); ctx.shadowBlur = 0;
      });
    }

    if (!reduce) requestAnimationFrame(frame);
  }
  frame();
})();
</script>
<?php endif ?>
</body>
</html>
<?php endif ?>
