<?php

declare(strict_types=1);
?>
<article class="shell identity-page">
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('workspace.active_eyebrow')) ?></p>
    <h1 id="workspace-heading" tabindex="-1"><?= $escape->escapeText($view->string('workspace_name')) ?></h1>
    <p class="lead"><?= $escape->escapeText($translator->trans('workspace.active_intro')) ?></p>
    <?= $renderer->render('fragments.workspace-panel', $view, $translator)->trustedHtml() ?>
</article>
