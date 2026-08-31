<?php

declare(strict_types=1);

use Qmdb\Shared\Presentation\View\ViewData;

$parent = $view->array('parent');
?>
<section id="geography-level-two-region" data-qmdb-fragment-root data-qmdb-geography-child-region aria-live="polite" aria-busy="false">
    <p class="field-hint"><?= $escape->escapeText($translator->trans('geography.children_for', ['name' => (string) ($parent['official_name'] ?? '')])) ?></p>
    <?= $renderer->render('components.geography-level-two-select', new ViewData(['children' => $view->list('children'), 'selected_public_id' => '']), $translator)->trustedHtml() ?>
</section>
