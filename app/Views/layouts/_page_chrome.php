<?php

/**
 * Pinned announcement banners + flash messages. Rendered by every shell at the
 * top of the page body, just before the page's own content. The dismiss / theme
 * JS lives in layouts/_foot.php.
 */
?>
<?php $pinned = array_filter(current_announcements(), static fn ($a) => (int) $a['pinned'] === 1); ?>
<?php if ($pinned): ?>
  <div class="announce-stack no-print" id="announceStack">
    <?php foreach ($pinned as $a): ?>
      <?php $lvl = in_array($a['level'], ['info', 'warning', 'success'], true) ? $a['level'] : 'info'; ?>
      <div class="announce announce-<?= $lvl ?>" data-ann="<?= (int) $a['id'] ?>-<?= strtotime((string) ($a['updated_at'] ?? $a['created_at'] ?? '')) ?>">
        <div class="announce-text">
          <strong><?= esc($a['title']) ?></strong>
          <?php if (! empty($a['body'])): ?><span><?= nl2br(esc($a['body'])) ?></span><?php endif ?>
        </div>
        <button type="button" class="announce-x" aria-label="<?= lang('Announce.dismiss') ?>">&times;</button>
      </div>
    <?php endforeach ?>
  </div>
  <script>
    // Hide already-dismissed banners at parse time (no flash on reload).
    (function () {
      try {
        var seen = JSON.parse(localStorage.getItem('sa-ann-dismissed') || '[]') || [],
            st = document.getElementById('announceStack');
        st.querySelectorAll('.announce').forEach(function (el) {
          if (seen.indexOf(el.getAttribute('data-ann')) !== -1) el.hidden = true;
        });
        if (!st.querySelector('.announce:not([hidden])')) st.hidden = true;
      } catch (e) {}
    })();
  </script>
<?php endif ?>
<?php if (session()->getFlashdata('message')): ?>
  <div class="alert alert-success"><?= esc(session()->getFlashdata('message')) ?></div>
<?php endif ?>
<?php if (session()->getFlashdata('error')): ?>
  <div class="alert alert-error"><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif ?>
<?php $errs = session()->getFlashdata('errors'); ?>
<?php if (! empty($errs) && is_array($errs)): ?>
  <div class="alert alert-error">
    <?= lang('App.please_fix') ?>
    <ul><?php foreach ($errs as $e): ?><li><?= esc($e) ?></li><?php endforeach ?></ul>
  </div>
<?php endif ?>
