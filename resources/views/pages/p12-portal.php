<?php

declare(strict_types=1);

?>
<article class="shell prose-page" data-qmdb-p12-portal>
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('p12.eyebrow')) ?></p>
    <h1><?= $escape->escapeText($translator->trans('p12.title')) ?></h1>
    <p><?= $escape->escapeText($translator->trans('p12.intro')) ?></p>
    <?= $renderer->render('fragments.p12-portal', $view, $translator)->trustedHtml() ?>
</article>
