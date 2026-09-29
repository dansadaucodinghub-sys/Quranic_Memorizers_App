<?php

declare(strict_types=1);
?>
<article class="public-inner-page public-about-page">
    <header class="public-page-hero shell">
        <p class="eyebrow"><?= $escape->escapeText($translator->trans('about.eyebrow')) ?></p>
        <h1><?= $escape->escapeText($translator->trans('system.about.heading')) ?></h1>
        <p class="lead"><?= $escape->escapeText($translator->trans('about.intro')) ?></p>
    </header>
    <section class="about-mission shell" aria-labelledby="about-mission-title">
        <div class="about-mission-copy">
            <p class="eyebrow"><?= $escape->escapeText($translator->trans('brand.promise')) ?></p>
            <h2 id="about-mission-title"><?= $escape->escapeText($translator->trans('about.mission.title')) ?></h2>
            <p><?= $escape->escapeText($translator->trans('about.mission.body')) ?></p>
        </div>
        <blockquote class="about-verse"><p><?= $escape->escapeText($translator->trans('home.verse')) ?></p><cite><?= $escape->escapeText($translator->trans('home.verse_reference')) ?></cite></blockquote>
    </section>
    <section class="about-values shell" aria-label="<?= $escape->escapeAttribute($translator->trans('system.about.heading')) ?>">
        <?php foreach (['integrity', 'dignity', 'access'] as $value): ?>
        <article><span class="value-mark" aria-hidden="true">✓</span><h2><?= $escape->escapeText($translator->trans('about.value.' . $value . '.title')) ?></h2><p><?= $escape->escapeText($translator->trans('about.value.' . $value . '.body')) ?></p></article>
        <?php endforeach; ?>
    </section>
    <section class="about-scope shell"><div><p class="eyebrow"><?= $escape->escapeText($translator->trans('home.audience.eyebrow')) ?></p><h2><?= $escape->escapeText($translator->trans('about.scope.title')) ?></h2><p><?= $escape->escapeText($translator->trans('about.scope.body')) ?></p></div><a class="button button-primary" href="/search"><?= $escape->escapeText($translator->trans('about.cta')) ?></a></section>
</article>
