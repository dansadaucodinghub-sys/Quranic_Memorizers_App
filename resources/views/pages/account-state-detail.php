<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('account_state.eyebrow')) ?></p>
    <h1><?= $escape->escapeText($translator->trans('account_state.detail_heading')) ?></h1>
    <?= $renderer->render('fragments.account-state-panel', $view, $translator)->trustedHtml() ?>
</article>
