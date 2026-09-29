<?php

declare(strict_types=1);

use Qmdb\Shared\Presentation\View\ViewData;
?>
<article class="public-inner-page status-page">
    <header class="public-page-hero shell"><p class="eyebrow"><?= $escape->escapeText($translator->trans('status.eyebrow')) ?></p><h1><?= $escape->escapeText($translator->trans('system.status.heading')) ?></h1><p class="lead"><?= $escape->escapeText($translator->trans('status.intro')) ?></p></header>
    <div class="shell status-page-card"><?= $renderer->render('fragments.system-status-card', new ViewData($view->array('status')), $translator)->trustedHtml() ?></div>
</article>
