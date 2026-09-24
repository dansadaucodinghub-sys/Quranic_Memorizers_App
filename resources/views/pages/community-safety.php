<?php

declare(strict_types=1);

?>
<article class="shell prose-page" data-qmdb-community-safety>
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('community.clip.eyebrow')) ?></p>
    <h1><?= $escape->escapeText($translator->trans('community.social.safety_title')) ?></h1>
    <?= $renderer->render('fragments.community-safety', $view, $translator)->trustedHtml() ?>
</article>
