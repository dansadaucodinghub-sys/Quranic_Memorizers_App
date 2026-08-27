<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('step_up.eyebrow')) ?></p>
    <h1 id="step-up-heading" tabindex="-1"><?= $escape->escapeText($translator->trans('step_up.heading')) ?></h1>
    <p class="lead"><?= $escape->escapeText($translator->trans('step_up.intro')) ?></p>
    <?= $renderer->render('fragments.account-step-up-form', $view, $translator)->trustedHtml() ?>
</article>
