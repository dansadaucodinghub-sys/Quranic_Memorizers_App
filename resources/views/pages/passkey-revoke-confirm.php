<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('passkey.eyebrow')) ?></p>
    <h1 id="passkey-revoke-heading" tabindex="-1"><?= $escape->escapeText($translator->trans('passkey.revoke_heading')) ?></h1>
    <?= $renderer->render('fragments.passkey-revoke-dialog', $view, $translator)->trustedHtml() ?>
</article>
