<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('auth_security.eyebrow')) ?></p>
    <h1 id="authentication-security-heading" tabindex="-1"><?= $escape->escapeText($translator->trans('auth_security.heading')) ?></h1>
    <p class="lead"><?= $escape->escapeText($translator->trans('auth_security.intro')) ?></p>
    <?= $renderer->render('fragments.account-authentication-security-panel', $view, $translator)->trustedHtml() ?>
</article>
