<?php

declare(strict_types=1);

?>
<article class="shell identity-page">
    <h1><?= $escape->escapeText($translator->trans('media.governance.title')) ?></h1>
    <?= $renderer->render('fragments.media-governance', $view, $translator)->trustedHtml() ?>
</article>
