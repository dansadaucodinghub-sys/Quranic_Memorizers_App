<?php

declare(strict_types=1);

use Qmdb\Shared\Presentation\View\ViewData;

$locale = $translator->locale()->value();
$path = $view->string('current_path');
?>
<header class="site-header">
    <div class="shell header-inner">
        <a class="brand" href="/?lang=<?= $escape->escapeAttribute($locale) ?>" aria-label="<?= $escape->escapeAttribute($translator->trans('app.name')) ?>">
            <span class="brand-mark" aria-hidden="true">Q</span>
            <span><strong><?= $escape->escapeText($translator->trans('app.name')) ?></strong><small><?= $escape->escapeText($translator->trans('app.tagline')) ?></small></span>
        </a>
        <nav aria-label="Primary"><ul class="primary-nav">
            <li><a href="/?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('nav.home')) ?></a></li>
            <li><a href="/system/about?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('nav.about')) ?></a></li>
            <li><a href="/system/status?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('nav.status')) ?></a></li>
        </ul></nav>
        <div class="header-controls">
            <?php if ($view->boolean('tenant_authenticated')): ?>
            <div class="workspace-context-links" data-qmdb-workspace-context
                 data-qmdb-tenant-context-version="<?= $escape->escapeAttribute((string)$view->integer('tenant_context_version')) ?>">
                <span><?= $escape->escapeText($translator->trans('workspace.context')) ?></span>
                <?php if ($view->string('tenant_workspace_name') !== ''): ?>
                    <a class="workspace-context-control" href="/workspace?lang=<?= $escape->escapeAttribute($locale) ?>">
                        <strong><?= $escape->escapeText($view->string('tenant_workspace_name')) ?></strong>
                    </a>
                    <a class="workspace-context-change" href="/account/workspaces?lang=<?= $escape->escapeAttribute($locale) ?>">
                        <?= $escape->escapeText($translator->trans('workspace.change')) ?>
                    </a>
                <?php else: ?>
                    <a class="workspace-context-control" href="/account/workspaces?lang=<?= $escape->escapeAttribute($locale) ?>">
                        <strong><?= $escape->escapeText($translator->trans('workspace.choose')) ?></strong>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?= $renderer->render('components.language-switcher', new ViewData(['current_path' => $path]), $translator)->trustedHtml() ?>
            <?= $renderer->render('components.theme-switcher', new ViewData(), $translator)->trustedHtml() ?>
        </div>
    </div>
</header>
