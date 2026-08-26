<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('account_security.eyebrow')) ?></p>
    <h1 id="account-security-heading" tabindex="-1"><?= $escape->escapeText($translator->trans('account_security.heading')) ?></h1>
    <p class="lead"><?= $escape->escapeText($translator->trans('account_security.intro')) ?></p>
    <?= $renderer->render('fragments.account-security-session-panel', $view, $translator)->trustedHtml() ?>
</article>
