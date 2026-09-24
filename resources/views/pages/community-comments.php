<?php

declare(strict_types=1);

?>
<article class="shell prose-page" data-qmdb-community-comments>
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('community.clip.eyebrow')) ?></p>
    <h1><?= $escape->escapeText($translator->trans('community.comments.title')) ?></h1>
    <?= $renderer->render('fragments.community-comments', $view, $translator)->trustedHtml() ?>
</article>
