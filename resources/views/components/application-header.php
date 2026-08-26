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
            <?= $renderer->render('components.language-switcher', new ViewData(['current_path' => $path]), $translator)->trustedHtml() ?>
            <?= $renderer->render('components.theme-switcher', new ViewData(), $translator)->trustedHtml() ?>
        </div>
    </div>
</header>
