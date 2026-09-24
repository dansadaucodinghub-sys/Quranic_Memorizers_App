<?php

declare(strict_types=1);

?>
<article class="shell prose-page" data-qmdb-community-clip>
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('community.clip.eyebrow')) ?></p>
    <h1><?= $escape->escapeText($translator->trans('community.clip.title')) ?></h1>
    <?= $renderer->render('fragments.community-clip', $view, $translator)->trustedHtml() ?>
</article>
