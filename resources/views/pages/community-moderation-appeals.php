<?php

declare(strict_types=1);

?>
<article class="shell prose-page" data-qmdb-community-moderation-appeals>
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('community.clip.eyebrow')) ?></p>
    <h1><?= $escape->escapeText($translator->trans('community.appeal.review_title')) ?></h1>
    <?= $renderer->render('fragments.community-moderation-appeals', $view, $translator)->trustedHtml() ?>
</article>
