<?php

declare(strict_types=1);

use Qmdb\Shared\Presentation\View\ViewData;

$locale = $translator->locale()->value();
?>
<section class="hero shell">
    <div><p class="eyebrow"><?= $escape->escapeText($translator->trans('home.hero_eyebrow')) ?></p><h1><?= $escape->escapeText($translator->trans('home.hero_title')) ?></h1><p class="lead"><?= $escape->escapeText($translator->trans('home.hero_body')) ?></p><div class="actions"><a class="button button-primary" href="/system/about?lang=<?= $escape->escapeAttribute($locale) ?>" data-qmdb-modal><?= $escape->escapeText($translator->trans('action.open_details')) ?></a><a class="button button-secondary" href="/system/status?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('nav.status')) ?></a></div></div>
    <aside class="foundation-panel"><p class="eyebrow"><?= $escape->escapeText($translator->trans('home.foundation_title')) ?></p><p><?= $escape->escapeText($translator->trans('home.foundation_body')) ?></p><dl class="facts"><div><dt><?= $escape->escapeText($translator->trans('system.about.phase')) ?></dt><dd><?= $escape->escapeText($view->string('phase')) ?></dd></div><div><dt><?= $escape->escapeText($translator->trans('system.about.batch')) ?></dt><dd><?= $escape->escapeText($view->string('batch')) ?></dd></div></dl></aside>
</section>
<div class="shell section-space"><?= $renderer->render('fragments.system-status-card', new ViewData($view->array('status')), $translator)->trustedHtml() ?></div>
