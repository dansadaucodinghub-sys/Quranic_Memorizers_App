<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <h1><?= $escape->escapeText($translator->trans('verification.confirm.heading')) ?></h1>
    <p class="lead"><?= $escape->escapeText($translator->trans('verification.confirm.intro')) ?></p>
    <?= $renderer->render('fragments.email-verification-confirm', $view, $translator)->trustedHtml() ?>
</article>
