<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('password_recovery.eyebrow')) ?></p>
    <h1><?= $escape->escapeText($translator->trans('password_recovery.heading')) ?></h1>
    <p class="lead"><?= $escape->escapeText($translator->trans('password_recovery.intro')) ?></p>
    <?= $renderer->render('fragments.password-recovery-request-form', $view, $translator)->trustedHtml() ?>
    <p><a href="/login"><?= $escape->escapeText($translator->trans('password_recovery.login')) ?></a></p>
    <p><a href="/register"><?= $escape->escapeText($translator->trans('login.register')) ?></a></p>
</article>
