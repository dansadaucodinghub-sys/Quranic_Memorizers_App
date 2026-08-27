<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('totp.eyebrow')) ?></p>
    <h1 id="totp-revoke-heading" tabindex="-1"><?= $escape->escapeText($translator->trans('totp.revoke_heading')) ?></h1>
    <?= $renderer->render('fragments.totp-revoke-dialog', $view, $translator)->trustedHtml() ?>
</article>
