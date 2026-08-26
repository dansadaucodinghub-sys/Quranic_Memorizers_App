<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('login.eyebrow')) ?></p>
    <h1><?= $escape->escapeText($translator->trans('login.heading')) ?></h1>
    <p class="lead"><?= $escape->escapeText($translator->trans('login.intro')) ?></p>
    <?= $renderer->render('fragments.login-form', $view, $translator)->trustedHtml() ?>
</article>
