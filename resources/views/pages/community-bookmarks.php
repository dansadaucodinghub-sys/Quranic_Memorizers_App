<?php

declare(strict_types=1);

?>
<article class="shell prose-page" data-qmdb-community-bookmarks>
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('community.clip.eyebrow')) ?></p>
    <h1><?= $escape->escapeText($translator->trans('community.bookmarks.title')) ?></h1>
    <?= $renderer->render('fragments.community-bookmarks', $view, $translator)->trustedHtml() ?>
</article>
