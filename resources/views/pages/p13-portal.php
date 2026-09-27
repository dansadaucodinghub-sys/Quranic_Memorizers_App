<?php

declare(strict_types=1);

?>
<article class="shell prose-page" data-qmdb-p13-portal>
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('p13.eyebrow')) ?></p>
    <h1><?= $escape->escapeText($translator->trans('p13.title')) ?></h1>
    <p><?= $escape->escapeText($translator->trans('p13.intro')) ?></p>
    <?= $renderer->render('fragments.p13-portal', $view, $translator)->trustedHtml() ?>
</article>
<script type="module" src="/assets/js/offline-venue-controller.js"></script>
