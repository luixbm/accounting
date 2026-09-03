<?php
// Clears the sticky filter for the current list (see sticky_filters() helper).
// Renders in a filter bar in place of the old "Reset" link.
?>
<a class="btn ghost filter-clear" href="<?= current_url() ?>?fclear=1"
   title="<?= lang('App.reset') ?>" aria-label="<?= lang('App.reset') ?>">
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
       stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
  <span><?= lang('App.reset') ?></span>
</a>
