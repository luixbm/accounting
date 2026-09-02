<?php
/**
 * @var list<array<string,mixed>> $cfDefs
 * @var array<string,string>      $cfValues
 */
if (empty($cfDefs)) {
    return;
}
?>
<fieldset>
  <legend>Additional information</legend>
  <div class="row">
    <?php foreach ($cfDefs as $f): ?>
      <?php $val = old('cf.' . $f['field_key'], $cfValues[$f['field_key']] ?? ''); ?>
      <div class="field" style="min-width:200px">
        <label>
          <?= esc($f['label']) ?><?= (int) $f['is_required'] === 1 ? ' *' : '' ?>
          <?php if ($f['help']): ?><span class="muted" style="font-weight:400"> — <?= esc($f['help']) ?></span><?php endif ?>
        </label>
        <?php if ($f['type'] === 'textarea'): ?>
          <textarea name="cf[<?= esc($f['field_key'], 'attr') ?>]" rows="2"><?= esc($val) ?></textarea>
        <?php elseif ($f['type'] === 'select'): ?>
          <select name="cf[<?= esc($f['field_key'], 'attr') ?>]">
            <option value=""><?= (int) $f['is_required'] === 1 ? '— choose —' : '—' ?></option>
            <?php foreach (preg_split('/\r\n|\r|\n/', (string) $f['options']) as $opt): ?>
              <?php $opt = trim($opt);
              if ($opt === '') {
                  continue;
              } ?>
              <option value="<?= esc($opt, 'attr') ?>" <?= (string) $val === $opt ? 'selected' : '' ?>><?= esc($opt) ?></option>
            <?php endforeach ?>
          </select>
        <?php elseif ($f['type'] === 'checkbox'): ?>
          <label class="inline" style="font-weight:400">
            <input type="hidden" name="cf[<?= esc($f['field_key'], 'attr') ?>]" value="0">
            <input type="checkbox" name="cf[<?= esc($f['field_key'], 'attr') ?>]" value="1" style="width:auto" <?= $val ? 'checked' : '' ?>>
            Yes
          </label>
        <?php elseif ($f['type'] === 'date'): ?>
          <input type="date" name="cf[<?= esc($f['field_key'], 'attr') ?>]" value="<?= esc($val) ?>">
        <?php elseif ($f['type'] === 'number'): ?>
          <input type="number" step="any" name="cf[<?= esc($f['field_key'], 'attr') ?>]" value="<?= esc($val) ?>">
        <?php else: ?>
          <input name="cf[<?= esc($f['field_key'], 'attr') ?>]" value="<?= esc($val) ?>">
        <?php endif ?>
      </div>
    <?php endforeach ?>
  </div>
</fieldset>
