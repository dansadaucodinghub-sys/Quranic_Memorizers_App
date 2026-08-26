<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <h1><?= $escape->escapeText($translator->trans('verification.resend.heading')) ?></h1>
    <p class="lead"><?= $escape->escapeText($translator->trans('verification.resend.intro')) ?></p>
    <?= $renderer->render('fragments.email-verification-resend-form', $view, $translator)->trustedHtml() ?>
</article>
