<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('recovery_codes.eyebrow')) ?></p>
    <h1 id="recovery-codes-heading" tabindex="-1"><?= $escape->escapeText($translator->trans('recovery_codes.status_heading')) ?></h1>
    <?= $renderer->render('fragments.recovery-codes-status', $view, $translator)->trustedHtml() ?>
</article>
