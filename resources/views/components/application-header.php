<?php

declare(strict_types=1);

use Qmdb\Shared\Presentation\View\ViewData;

$locale = $translator->locale()->value();
$path = $view->string('current_path');
$authenticated = $view->boolean('tenant_authenticated');
$isCurrent = static function (string $route) use ($path): bool {
    if ($route === '/') {
        return $path === '/';
    }

    return $path === $route || str_starts_with($path, $route . '/');
};
$navigation = [
    ['route' => '/', 'href' => '/', 'label' => 'nav.home'],
    ['route' => '/search', 'href' => '/search', 'label' => 'nav.discover'],
    ['route' => '/statistics', 'href' => '/statistics', 'label' => 'nav.results'],
    ['route' => '/community', 'href' => '/community', 'label' => 'nav.community'],
    ['route' => '/quran', 'href' => '/quran', 'label' => 'nav.quran'],
    ['route' => '/system/about', 'href' => '/system/about', 'label' => 'nav.about'],
];
?>
<header class="site-header">
    <div class="brand-ribbon">
        <p class="visually-hidden"><?= $escape->escapeText($translator->trans('brand.promise')) ?></p>
        <div class="brand-marquee" aria-hidden="true">
            <div class="brand-marquee-track">
                <?php for ($group = 0; $group < 2; $group++) : ?>
                    <div class="brand-marquee-group">
                        <?php for ($item = 0; $item < 4; $item++) : ?>
                            <span class="brand-marquee-item"><i></i><?= $escape->escapeText($translator->trans('brand.promise')) ?></span>
                        <?php endfor; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
    <div class="shell header-inner">
        <a class="brand" href="/?lang=<?= $escape->escapeAttribute($locale) ?>" aria-label="<?= $escape->escapeAttribute($translator->trans('app.name')) ?>">
            <img class="brand-mark" src="/assets/brand/musabaqahub-app-icon.png" width="62" height="62" alt="">
            <span class="brand-copy"><strong><span>Musabaqa</span><em>Hub</em></strong><small><?= $escape->escapeText($translator->trans('app.tagline')) ?></small></span>
        </a>

        <nav class="primary-navigation" aria-label="<?= $escape->escapeAttribute($translator->trans('nav.primary')) ?>">
            <ul class="primary-nav">
                <?php foreach ($navigation as $item) : ?>
                    <li><a<?= $isCurrent($item['route']) ? ' aria-current="page"' : '' ?> href="<?= $escape->escapeAttribute($item['href']) ?>?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans($item['label'])) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="header-actions">
            <?php if ($authenticated) : ?>
                <details class="account-menu" data-qmdb-workspace-context data-qmdb-tenant-context-version="<?= $escape->escapeAttribute((string) $view->integer('tenant_context_version')) ?>">
                    <summary><span class="account-avatar" aria-hidden="true"><?= $view->string('tenant_workspace_name') !== '' ? $escape->escapeText(mb_strtoupper(mb_substr($view->string('tenant_workspace_name'), 0, 1))) : 'M' ?></span><span><?= $escape->escapeText($view->string('tenant_workspace_name') !== '' ? $view->string('tenant_workspace_name') : $translator->trans('workspace.context')) ?></span></summary>
                    <div class="account-menu-panel">
                        <?php if ($view->string('tenant_workspace_name') !== '') : ?>
                            <a class="workspace-context-control" href="/workspace?lang=<?= $escape->escapeAttribute($locale) ?>"><strong><?= $escape->escapeText($view->string('tenant_workspace_name')) ?></strong></a>
                            <a href="/account/workspaces?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('workspace.change')) ?></a>
                        <?php else : ?>
                            <a class="workspace-context-control" href="/account/workspaces?lang=<?= $escape->escapeAttribute($locale) ?>"><strong><?= $escape->escapeText($translator->trans('workspace.choose')) ?></strong></a>
                        <?php endif; ?>
                        <span class="account-menu-label"><?= $escape->escapeText($translator->trans('workspace.context')) ?></span>
                        <a href="/account/community/profile?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('community.profile.title')) ?></a>
                        <a href="/workspace/community/clips?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('community.creator.title')) ?></a>
                        <a href="/account/community/bookmarks?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('community.bookmarks.title')) ?></a>
                        <a href="/account/community/safety?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('community.social.safety_title')) ?></a>
                    </div>
                </details>
            <?php else : ?>
                <div class="authentication-actions">
                    <a class="header-sign-in" href="/login?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('nav.sign_in')) ?></a>
                    <a class="button button-primary header-register" href="/register?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('nav.register')) ?></a>
                </div>
            <?php endif; ?>
            <details class="mobile-navigation">
                <summary aria-label="<?= $escape->escapeAttribute($translator->trans('nav.menu')) ?>"><span aria-hidden="true"></span><?= $escape->escapeText($translator->trans('nav.menu')) ?></summary>
                <div class="mobile-navigation-panel">
                    <nav aria-label="<?= $escape->escapeAttribute($translator->trans('nav.primary')) ?>">
                        <?php foreach ($navigation as $item) : ?>
                            <a<?= $isCurrent($item['route']) ? ' aria-current="page"' : '' ?> href="<?= $escape->escapeAttribute($item['href']) ?>?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans($item['label'])) ?></a>
                        <?php endforeach; ?>
                    </nav>
                    <?php if (!$authenticated) : ?>
                        <div class="mobile-authentication-actions"><a href="/login?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('nav.sign_in')) ?></a><a href="/register?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('nav.register')) ?></a></div>
                    <?php else : ?>
                        <a class="mobile-workspace-link" href="/workspace?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('workspace.context')) ?></a>
                    <?php endif; ?>
                </div>
            </details>
        </div>

        <div class="header-preferences">
            <?= $renderer->render('components.language-switcher', new ViewData(['current_path' => $path]), $translator)->trustedHtml() ?>
            <?= $renderer->render('components.theme-switcher', new ViewData(), $translator)->trustedHtml() ?>
        </div>
    </div>
</header>
