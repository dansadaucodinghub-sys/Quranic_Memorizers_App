<?php

declare(strict_types=1);

use Qmdb\Shared\Presentation\View\ViewData;

$locale = $translator->locale()->value();
?>
<section class="hero shell">
    <div class="hero-copy">
        <p class="eyebrow"><?= $escape->escapeText($translator->trans('home.hero_eyebrow')) ?></p>
        <h1><?= $escape->escapeText($translator->trans('home.hero_title')) ?></h1>
        <p class="lead"><?= $escape->escapeText($translator->trans('home.hero_body')) ?></p>
        <div class="actions">
            <a class="button button-primary" href="/community?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('nav.community')) ?></a>
            <a class="button button-secondary" href="/system/about?lang=<?= $escape->escapeAttribute($locale) ?>" data-qmdb-modal><?= $escape->escapeText($translator->trans('action.open_details')) ?></a>
        </div>
        <div class="hero-capabilities" aria-label="<?= $escape->escapeAttribute($translator->trans('home.features_label')) ?>">
            <span><?= $escape->escapeText($translator->trans('home.feature.competitions')) ?></span>
            <span><?= $escape->escapeText($translator->trans('home.feature.results')) ?></span>
            <span><?= $escape->escapeText($translator->trans('home.feature.participants')) ?></span>
            <span><?= $escape->escapeText($translator->trans('home.feature.community')) ?></span>
        </div>
    </div>
    <aside class="hero-showcase">
        <div class="hero-showcase-brand"><img src="/assets/brand/musabaqahub-logo.png" alt="<?= $escape->escapeAttribute($translator->trans('app.name')) ?>"></div>
        <div class="foundation-panel">
            <p class="eyebrow"><?= $escape->escapeText($translator->trans('home.foundation_title')) ?></p>
            <p><?= $escape->escapeText($translator->trans('home.foundation_body')) ?></p>
            <dl class="facts"><div><dt><?= $escape->escapeText($translator->trans('system.about.phase')) ?></dt><dd><?= $escape->escapeText($view->string('phase')) ?></dd></div><div><dt><?= $escape->escapeText($translator->trans('system.about.batch')) ?></dt><dd><?= $escape->escapeText($view->string('batch')) ?></dd></div></dl>
        </div>
    </aside>
</section>
<section class="shell home-assurance section-space">
    <div class="section-heading"><p class="eyebrow"><?= $escape->escapeText($translator->trans('home.assurance_eyebrow')) ?></p><h2><?= $escape->escapeText($translator->trans('home.assurance_title')) ?></h2></div>
    <?= $renderer->render('fragments.system-status-card', new ViewData($view->array('status')), $translator)->trustedHtml() ?>
</section>
