<?php

declare(strict_types=1);

$children = $view->list('children');
$selected = $view->string('selected_public_id');
?>
<label for="geography-level-two"><?= $escape->escapeText($translator->trans('geography.choose_level_two')) ?></label>
<select id="geography-level-two" name="geography_level_two" data-qmdb-geography-level-two>
    <option value=""><?= $escape->escapeText($translator->trans('geography.choose_level_two')) ?></option>
    <?php foreach ($children as $child) : ?>
        <?php if (!is_array($child) || !is_string($child['public_id'] ?? null) || !is_string($child['official_name'] ?? null)) : ?>
            <?php throw new RuntimeException('Geography level-two view data is invalid.'); ?>
        <?php endif; ?>
        <option value="<?= $escape->escapeAttribute($child['public_id']) ?>"<?= $child['public_id'] === $selected ? ' selected' : '' ?>><?= $escape->escapeText($child['official_name']) ?></option>
    <?php endforeach; ?>
</select>
