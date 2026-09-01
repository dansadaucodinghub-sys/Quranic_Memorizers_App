<?php

declare(strict_types=1);

use Qmdb\Shared\Presentation\View\ViewData;

$parent = $view->array('parent');
$regionId = $view->string('child_region_id') ?: 'geography-level-two-region';
?>
<section id="<?= $escape->escapeAttribute($regionId) ?>" data-qmdb-fragment-root data-qmdb-geography-child-region aria-live="polite" aria-busy="false">
    <p class="field-hint"><?= $escape->escapeText($translator->trans('geography.children_for', ['name' => (string) ($parent['official_name'] ?? '')])) ?></p>
    <?= $renderer->render('components.geography-level-two-select', new ViewData([
        'children' => $view->list('children'),
        'selected_public_id' => $view->string('selected_public_id'),
        'field_name' => $view->string('child_field_name') ?: 'geography_level_two',
        'input_id' => $view->string('child_input_id') ?: 'geography-level-two',
    ]), $translator)->trustedHtml() ?>
</section>
