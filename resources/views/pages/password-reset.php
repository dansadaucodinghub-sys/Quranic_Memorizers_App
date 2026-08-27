<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('password_reset.eyebrow')) ?></p>
    <h1><?= $escape->escapeText($translator->trans('password_reset.heading')) ?></h1>
    <p class="lead"><?= $escape->escapeText($translator->trans('password_reset.intro')) ?></p>
    <?= $renderer->render('fragments.password-reset-form', $view, $translator)->trustedHtml() ?>
</article>
