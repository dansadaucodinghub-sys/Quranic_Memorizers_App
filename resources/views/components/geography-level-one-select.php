<?php

declare(strict_types=1);

$areas = $view->list('areas');
$selected = $view->string('selected_public_id');
?>
<label for="geography-level-one"><?= $escape->escapeText($translator->trans('geography.choose_level_one')) ?></label>
<select id="geography-level-one" name="geography_level_one" data-qmdb-geography-level-one data-qmdb-geography-child-target="#geography-level-two-region" data-qmdb-geography-loading-message="<?= $escape->escapeAttribute($translator->trans('geography.loading_areas')) ?>" data-qmdb-geography-loaded-message="<?= $escape->escapeAttribute($translator->trans('geography.areas_loaded')) ?>" data-qmdb-geography-error-message="<?= $escape->escapeAttribute($translator->trans('geography.unable_to_load_areas')) ?>" data-qmdb-geography-cleared-message="<?= $escape->escapeAttribute($translator->trans('geography.no_area_selected')) ?>">
    <option value=""><?= $escape->escapeText($translator->trans('geography.choose_level_one')) ?></option>
    <?php foreach ($areas as $area) : ?>
        <?php if (!is_array($area) || !is_string($area['public_id'] ?? null) || !is_string($area['official_name'] ?? null)) : ?>
            <?php throw new RuntimeException('Geography level-one view data is invalid.'); ?>
        <?php endif; ?>
        <option value="<?= $escape->escapeAttribute($area['public_id']) ?>"<?= $area['public_id'] === $selected ? ' selected' : '' ?>><?= $escape->escapeText($area['official_name']) ?></option>
    <?php endforeach; ?>
</select>
