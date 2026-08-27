<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('mfa.eyebrow')) ?></p>
    <h1 id="mfa-login-heading" tabindex="-1"><?= $escape->escapeText($translator->trans('mfa.login_heading')) ?></h1>
    <p class="lead"><?= $escape->escapeText($translator->trans('mfa.login_intro')) ?></p>
    <?= $renderer->render('fragments.login-mfa-form', $view, $translator)->trustedHtml() ?>
</article>
