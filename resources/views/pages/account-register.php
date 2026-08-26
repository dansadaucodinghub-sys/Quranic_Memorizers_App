<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('registration.eyebrow')) ?></p>
    <h1><?= $escape->escapeText($translator->trans('registration.heading')) ?></h1>
    <p class="lead"><?= $escape->escapeText($translator->trans('registration.intro')) ?></p>
    <?= $renderer->render('fragments.account-register-form', $view, $translator)->trustedHtml() ?>
</article>
