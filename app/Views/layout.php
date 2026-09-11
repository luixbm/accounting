<?php

/**
 * Outer HTML shell. The visible chrome (nav, header) lives in a per-design
 * shell under layouts/ — `active_design()` picks classic (left sidebar) or
 * modern (top bar). Colour theme and language still layer on top of either.
 *
 * @var string $title
 */
$design = active_design();

// Cache-bust the two stylesheets off their own mtime, so a CSS edit takes
// effect on the next normal load instead of needing a hard refresh.
$assetV = static function (string $rel): string {
    $path = FCPATH . 'assets/' . $rel;
    $v    = is_file($path) ? filemtime($path) : time();

    return base_url('assets/' . $rel) . '?v=' . $v;
};
?>
<!DOCTYPE html>
<html lang="<?= esc(app_locale()) ?>" data-theme="<?= esc(app_theme()) ?>" data-design="<?= esc($design) ?>" data-thead="<?= esc(table_header_style()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($title ?? 'LuixSpace') ?> &middot; <?= esc(company_name()) ?></title>
<link rel="stylesheet" href="<?= $assetV('app.css') ?>">
<link rel="stylesheet" href="<?= $assetV('design-' . $design . '.css') ?>">
<script>
  (function () {
    try {
      var t = localStorage.getItem('sa-theme');
      if (t) document.documentElement.setAttribute('data-theme', t);
    } catch (e) {}
  })();
</script>
</head>
<body>
<?php include __DIR__ . '/layouts/' . $design . '.php'; ?>
<?php include __DIR__ . '/layouts/_foot.php'; ?>
</body>
</html>
