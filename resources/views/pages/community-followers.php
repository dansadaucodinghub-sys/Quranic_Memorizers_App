<?php

declare(strict_types=1);

?>
<article class="shell prose-page" data-qmdb-community-followers>
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('community.clip.eyebrow')) ?></p>
    <h1><?= $escape->escapeText($translator->trans('community.social.followers_title')) ?></h1>
    <?= $renderer->render('fragments.community-followers', $view, $translator)->trustedHtml() ?>
</article>
