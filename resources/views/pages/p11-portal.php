<?php

declare(strict_types=1);

?>
<article class="shell prose-page" data-qmdb-p11-portal>
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('analytics.eyebrow')) ?></p>
    <h1><?= $escape->escapeText($translator->trans('analytics.title')) ?></h1>
    <p><?= $escape->escapeText($translator->trans('analytics.description')) ?></p>
    <?= $renderer->render('fragments.p11-portal', $view, $translator)->trustedHtml() ?>
</article>
