<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('recovery_codes.eyebrow')) ?></p>
    <h1 id="recovery-codes-heading" tabindex="-1"><?= $escape->escapeText($translator->trans('recovery_codes.one_time_heading')) ?></h1>
    <p class="lead"><?= $escape->escapeText($translator->trans('recovery_codes.one_time_intro')) ?></p>
    <?= $renderer->render('fragments.recovery-codes-one-time', $view, $translator)->trustedHtml() ?>
</article>
