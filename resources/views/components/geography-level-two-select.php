<?php

declare(strict_types=1);

$children = $view->list('children');
$selected = $view->string('selected_public_id');
$fieldName = $view->string('field_name') ?: 'geography_level_two';
$inputId = $view->string('input_id') ?: 'geography-level-two';
?>
<label for="<?= $escape->escapeAttribute($inputId) ?>"><?= $escape->escapeText($translator->trans('geography.choose_level_two')) ?></label>
<select id="<?= $escape->escapeAttribute($inputId) ?>" name="<?= $escape->escapeAttribute($fieldName) ?>" data-qmdb-geography-level-two>
    <option value=""><?= $escape->escapeText($translator->trans('geography.choose_level_two')) ?></option>
    <?php foreach ($children as $child) : ?>
        <?php if (!is_array($child) || !is_string($child['public_id'] ?? null) || !is_string($child['official_name'] ?? null)) : ?>
            <?php throw new RuntimeException('Geography level-two view data is invalid.'); ?>
        <?php endif; ?>
        <option value="<?= $escape->escapeAttribute($child['public_id']) ?>"<?= $child['public_id'] === $selected ? ' selected' : '' ?>><?= $escape->escapeText($child['official_name']) ?></option>
    <?php endforeach; ?>
</select>
