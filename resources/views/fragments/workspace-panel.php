<?php

declare(strict_types=1);
?>
<section class="foundation-panel" data-qmdb-fragment-root aria-labelledby="workspace-context-heading">
    <h2 id="workspace-context-heading"><?= $escape->escapeText($translator->trans('workspace.context_confirmed')) ?></h2>
    <dl class="facts">
        <div><dt><?= $escape->escapeText($translator->trans('workspace.name')) ?></dt><dd><?= $escape->escapeText($view->string('workspace_name')) ?></dd></div>
        <div><dt><?= $escape->escapeText($translator->trans('workspace.workspace_status')) ?></dt><dd><?= $escape->escapeText($translator->trans('workspace.status_active')) ?></dd></div>
        <div><dt><?= $escape->escapeText($translator->trans('workspace.membership_status')) ?></dt><dd><?= $escape->escapeText($translator->trans('workspace.status_active')) ?></dd></div>
        <div><dt><?= $escape->escapeText($translator->trans('workspace.context_version')) ?></dt><dd><?= $escape->escapeText((string)$view->integer('tenant_context_version')) ?></dd></div>
    </dl>
    <p class="status-banner" role="status"><?= $escape->escapeText($translator->trans('workspace.context_active')) ?></p>
    <div class="actions">
        <a class="button button-secondary" href="/account/workspaces"><?= $escape->escapeText($translator->trans('workspace.change')) ?></a>
        <a class="button button-quiet" href="/account/security/sessions"><?= $escape->escapeText($translator->trans('workspace.account_security')) ?></a>
    </div>
</section>
