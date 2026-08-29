<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('workspace.eyebrow')) ?></p>
    <h1 id="account-workspaces-heading" tabindex="-1"><?= $escape->escapeText($translator->trans('workspace.heading')) ?></h1>
    <p class="lead"><?= $escape->escapeText($translator->trans('workspace.intro')) ?></p>
    <?= $renderer->render('fragments.account-workspaces-panel', $view, $translator)->trustedHtml() ?>
</article>
