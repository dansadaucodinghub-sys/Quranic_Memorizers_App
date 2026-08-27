<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('mfa.eyebrow')) ?></p>
    <h1 id="mfa-disable-heading" tabindex="-1"><?= $escape->escapeText($translator->trans('mfa.disable_heading')) ?></h1>
    <?= $renderer->render('fragments.mfa-disable-dialog', $view, $translator)->trustedHtml() ?>
</article>
