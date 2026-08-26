<?php

declare(strict_types=1);

use Qmdb\Shared\Presentation\Html\SafeHtml;
use Qmdb\Shared\Presentation\View\ViewData;

$content = $view->value('content');
if (!$content instanceof SafeHtml) {
    throw new RuntimeException('Page content is invalid.');
}
$shared = new ViewData(['current_path' => $view->string('current_path')]);
?><!doctype html>
<html lang="<?= $escape->escapeAttribute($view->string('locale')) ?>" dir="<?= $escape->escapeAttribute($view->string('direction')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape->escapeText($view->string('title')) ?></title>
    <?php foreach (['tokens.css', 'base.css', 'layout.css', 'components.css', 'themes.css', 'utilities.css'] as $cssFile): ?>
    <link rel="stylesheet" href="<?= $escape->escapeAttribute($assets->css($cssFile)->value()) ?>">
    <?php endforeach; ?>
    <script nonce="<?= $escape->escapeAttribute($view->string('nonce')) ?>">(()=>{try{const k='qmdb.theme',a=['system','light','dark','high-contrast','emerald-gold'],v=localStorage.getItem(k);document.documentElement.dataset.theme=a.includes(v)?v:'system'}catch(e){document.documentElement.dataset.theme='system'}})();</script>
    <script type="module" src="<?= $escape->escapeAttribute($assets->js('app.js')->value()) ?>"></script>
</head>
<body>
    <a class="skip-link" href="#main-content"><?= $escape->escapeText($translator->trans('a11y.skip_to_content')) ?></a>
    <?= $renderer->render('components.application-header', $shared, $translator)->trustedHtml() ?>
    <main id="main-content" class="site-main" tabindex="-1"><?= $content->trustedHtml() ?></main>
    <?= $renderer->render('components.application-footer', new ViewData(), $translator)->trustedHtml() ?>
    <?= $renderer->render('components.live-region', new ViewData(), $translator)->trustedHtml() ?>
    <?= $renderer->render('components.modal-shell', new ViewData(), $translator)->trustedHtml() ?>
    <noscript><p class="noscript-notice"><?= $escape->escapeText($translator->trans('noscript.notice')) ?></p></noscript>
</body>
</html>
