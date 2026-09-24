<?php

declare(strict_types=1);

?>
<article class="shell prose-page" data-qmdb-community-creator-clips>
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('community.clip.eyebrow')) ?></p>
    <h1><?= $escape->escapeText($translator->trans('community.creator.title')) ?></h1>
    <?= $renderer->render('fragments.community-creator-clips', $view, $translator)->trustedHtml() ?>
</article>
