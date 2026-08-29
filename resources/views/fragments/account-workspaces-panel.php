<?php

declare(strict_types=1);

$workspaces = $view->list('workspaces');
$version = $view->integer('tenant_context_version');
?>
<section id="account-workspaces-panel" class="workspace-selector" data-qmdb-fragment-root
         data-qmdb-tenant-context-version="<?= $escape->escapeAttribute((string)$version) ?>"
         aria-labelledby="workspace-selector-heading">
    <div class="section-heading">
        <div>
            <h2 id="workspace-selector-heading"><?= $escape->escapeText($translator->trans('workspace.available')) ?></h2>
            <p><?= $escape->escapeText($view->string('current_workspace_name') !== ''
                ? $translator->trans('workspace.current', ['name' => $view->string('current_workspace_name')])
                : $translator->trans('workspace.none_selected')) ?></p>
        </div>
        <?php if ($view->string('current_workspace_id') !== ''): ?>
        <form method="post" action="/account/workspaces/clear" data-qmdb-progressive-form data-qmdb-workspace-mutation>
            <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('clear_csrf_token')) ?>">
            <input type="hidden" name="tenant_context_version" value="<?= $escape->escapeAttribute((string)$version) ?>">
            <button class="button button-quiet" type="submit"><?= $escape->escapeText($translator->trans('workspace.clear')) ?></button>
        </form>
        <?php endif; ?>
    </div>
    <?php if ($workspaces === []): ?>
        <p class="workspace-empty" role="status"><?= $escape->escapeText($translator->trans('workspace.empty')) ?></p>
    <?php else: ?>
        <div class="workspace-list">
        <?php foreach ($workspaces as $workspace): ?>
            <?php if (!is_array($workspace) || !is_string($workspace['id'] ?? null) || !is_string($workspace['name'] ?? null)) { continue; } ?>
            <form method="post" action="/account/workspaces/switch" data-qmdb-progressive-form data-qmdb-workspace-mutation
                  class="workspace-option<?= ($workspace['selected'] ?? false) === true ? ' is-selected' : '' ?>">
                <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('switch_csrf_token')) ?>">
                <input type="hidden" name="tenant_context_version" value="<?= $escape->escapeAttribute((string)$version) ?>">
                <input type="hidden" name="workspace_id" value="<?= $escape->escapeAttribute($workspace['id']) ?>">
                <span><strong><?= $escape->escapeText($workspace['name']) ?></strong>
                    <small><?= $escape->escapeText(($workspace['selected'] ?? false) === true
                        ? $translator->trans('workspace.selected') : $translator->trans('workspace.select_hint')) ?></small></span>
                <button class="button button-primary" type="submit"<?= ($workspace['selected'] ?? false) === true ? ' disabled' : '' ?>>
                    <?= $escape->escapeText(($workspace['selected'] ?? false) === true
                        ? $translator->trans('workspace.selected') : $translator->trans('workspace.select')) ?>
                </button>
            </form>
        <?php endforeach; ?>
        </div>
        <?php if ($view->string('next_cursor') !== ''): ?>
            <nav class="workspace-pagination"
                 aria-label="<?= $escape->escapeAttribute($translator->trans('workspace.pagination')) ?>">
                <a class="button button-secondary"
                   href="/account/workspaces?cursor=<?= $escape->escapeAttribute($view->string('next_cursor')) ?>">
                    <?= $escape->escapeText($translator->trans('workspace.next')) ?>
                </a>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
    <div class="actions workspace-account-actions">
        <a class="button button-quiet" href="/account/security/sessions"><?= $escape->escapeText($translator->trans('workspace.account_security')) ?></a>
    </div>
</section>
