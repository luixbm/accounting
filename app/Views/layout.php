<?php

/**
 * Outer HTML shell. The visible chrome (nav, header) lives in a per-design
 * shell under layouts/ — `active_design()` picks classic (left sidebar) or
 * modern (top bar). Colour theme and language still layer on top of either.
 *
 * @var string $title
 */
$design = active_design();
?>
<!DOCTYPE html>
<html lang="<?= esc(app_locale()) ?>" data-theme="<?= esc(app_theme()) ?>" data-design="<?= esc($design) ?>" data-thead="<?= esc(table_header_style()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($title ?? 'LuixSpace') ?> &middot; <?= esc(company_name()) ?></title>
<link rel="stylesheet" href="<?= base_url('assets/app.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/design-' . $design . '.css') ?>">
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
