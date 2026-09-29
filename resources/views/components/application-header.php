<?php

declare(strict_types=1);

use Qmdb\Shared\Presentation\View\ViewData;

$locale = $translator->locale()->value();
$path = $view->string('current_path');
$isCurrent = static function (string $route) use ($path): bool {
    if ($route === '/') {
        return $path === '/';
    }

    return $path === $route || str_starts_with($path, $route . '/');
};
?>
<header class="site-header">
    <div class="brand-ribbon"><div class="shell"><?= $escape->escapeText($translator->trans('brand.promise')) ?></div></div>
    <div class="shell header-inner">
        <a class="brand" href="/?lang=<?= $escape->escapeAttribute($locale) ?>" aria-label="<?= $escape->escapeAttribute($translator->trans('app.name')) ?>">
            <img class="brand-mark" src="/assets/brand/musabaqahub-app-icon.png" width="62" height="62" alt="">
            <span class="brand-copy"><strong><span>Musabaqa</span><em>Hub</em></strong><small><?= $escape->escapeText($translator->trans('app.tagline')) ?></small></span>
        </a>
        <nav class="primary-navigation" aria-label="Primary"><ul class="primary-nav">
            <li><a<?= $isCurrent('/') ? ' aria-current="page"' : '' ?> href="/?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('nav.home')) ?></a></li>
            <li><a<?= $isCurrent('/system/about') ? ' aria-current="page"' : '' ?> href="/system/about?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('nav.about')) ?></a></li>
            <li><a<?= $isCurrent('/community') ? ' aria-current="page"' : '' ?> href="/community?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('nav.community')) ?></a></li>
            <li><a<?= $isCurrent('/system/status') ? ' aria-current="page"' : '' ?> href="/system/status?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('nav.status')) ?></a></li>
        </ul></nav>
        <div class="header-controls">
            <?php if ($view->boolean('tenant_authenticated')) : ?>
            <details class="account-menu" data-qmdb-workspace-context
                 data-qmdb-tenant-context-version="<?= $escape->escapeAttribute((string)$view->integer('tenant_context_version')) ?>">
                <summary><span class="account-avatar" aria-hidden="true"><?= $view->string('tenant_workspace_name') !== '' ? $escape->escapeText(mb_strtoupper(mb_substr($view->string('tenant_workspace_name'), 0, 1))) : 'M' ?></span><span><?= $escape->escapeText($view->string('tenant_workspace_name') !== '' ? $view->string('tenant_workspace_name') : $translator->trans('workspace.context')) ?></span></summary>
                <div class="account-menu-panel">
                <?php if ($view->string('tenant_workspace_name') !== '') : ?>
                    <a class="workspace-context-control" href="/workspace?lang=<?= $escape->escapeAttribute($locale) ?>">
                        <strong><?= $escape->escapeText($view->string('tenant_workspace_name')) ?></strong>
                    </a>
                    <a class="workspace-context-change" href="/account/workspaces?lang=<?= $escape->escapeAttribute($locale) ?>">
                        <?= $escape->escapeText($translator->trans('workspace.change')) ?>
                    </a>
                <?php else : ?>
                    <a class="workspace-context-control" href="/account/workspaces?lang=<?= $escape->escapeAttribute($locale) ?>">
                        <strong><?= $escape->escapeText($translator->trans('workspace.choose')) ?></strong>
                    </a>
                <?php endif; ?>
                    <span class="account-menu-label"><?= $escape->escapeText($translator->trans('workspace.context')) ?></span>
                    <a href="/account/community/profile?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('community.profile.title')) ?></a>
                    <a href="/workspace/community/clips?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('community.creator.title')) ?></a>
                    <a href="/account/community/safety?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('community.social.safety_title')) ?></a>
                    <a href="/account/community/followers?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('community.social.followers_title')) ?></a>
                    <a href="/account/community/following?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('community.social.following_title')) ?></a>
                    <a href="/account/community/bookmarks?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('community.bookmarks.title')) ?></a>
                    <a href="/account/community/appeals?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('community.appeal.title')) ?></a>
                </div>
            </details>
            <?php endif; ?>
            <?= $renderer->render('components.language-switcher', new ViewData(['current_path' => $path]), $translator)->trustedHtml() ?>
            <?= $renderer->render('components.theme-switcher', new ViewData(), $translator)->trustedHtml() ?>
        </div>
    </div>
</header>
