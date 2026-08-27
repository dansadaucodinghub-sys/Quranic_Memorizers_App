<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('totp.eyebrow')) ?></p>
    <h1 id="totp-heading" tabindex="-1"><?= $escape->escapeText($translator->trans('totp.confirm_heading')) ?></h1>
    <p class="lead"><?= $escape->escapeText($translator->trans('totp.confirm_intro')) ?></p>
    <?= $renderer->render('fragments.totp-enrollment-confirm-form', $view, $translator)->trustedHtml() ?>
</article>
