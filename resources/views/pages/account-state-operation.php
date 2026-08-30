<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <h1><?= $escape->escapeText($translator->trans('account_state.operation_heading')) ?></h1>
    <?= $renderer->render('fragments.account-state-operation-form', $view, $translator)->trustedHtml() ?>
</article>
