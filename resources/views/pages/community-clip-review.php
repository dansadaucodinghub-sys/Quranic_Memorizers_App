<?php

declare(strict_types=1);

?>
<article class="shell prose-page" data-qmdb-community-clip-review>
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('community.clip.eyebrow')) ?></p>
    <h1><?= $escape->escapeText($translator->trans('community.review.title')) ?></h1>
    <?= $renderer->render('fragments.community-clip-review', $view, $translator)->trustedHtml() ?>
</article>
