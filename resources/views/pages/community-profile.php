<?php

declare(strict_types=1);

?>
<article class="shell prose-page" data-qmdb-community-profile>
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('community.clip.eyebrow')) ?></p>
    <h1><?= $escape->escapeText($translator->trans('community.profile.title')) ?></h1>
    <?= $renderer->render('fragments.community-profile', $view, $translator)->trustedHtml() ?>
</article>
