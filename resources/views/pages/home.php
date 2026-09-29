<?php

declare(strict_types=1);

$locale = $translator->locale()->value();
?>
<section class="public-hero">
    <div class="shell public-hero-grid">
        <div class="public-hero-copy">
            <p class="public-kicker"><span aria-hidden="true"></span><?= $escape->escapeText($translator->trans('home.hero_eyebrow')) ?></p>
            <h1><?= $escape->escapeText($translator->trans('home.hero_title')) ?></h1>
            <p class="public-hero-lead"><?= $escape->escapeText($translator->trans('home.hero_body')) ?></p>
            <div class="actions public-hero-actions">
                <a class="button button-primary button-large" href="/search?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('home.hero_primary')) ?><span aria-hidden="true">→</span></a>
                <a class="button button-secondary button-large" href="/statistics?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('home.hero_secondary')) ?></a>
            </div>
            <ul class="public-trust-list" aria-label="<?= $escape->escapeAttribute($translator->trans('home.trust_label')) ?>">
                <li><span aria-hidden="true">✓</span><?= $escape->escapeText($translator->trans('home.trust.verified')) ?></li>
                <li><span aria-hidden="true">✓</span><?= $escape->escapeText($translator->trans('home.trust.bilingual')) ?></li>
                <li><span aria-hidden="true">✓</span><?= $escape->escapeText($translator->trans('home.trust.accessible')) ?></li>
            </ul>
        </div>

        <div class="competition-preview" aria-label="<?= $escape->escapeAttribute($translator->trans('home.preview.label')) ?>">
            <div class="competition-preview-window">
                <div class="preview-device-top" aria-hidden="true"><span class="preview-device-time">9:41</span><span class="preview-device-island"></span><span class="preview-device-status">▮▮▮ ◉ ▰</span></div>
                <div class="preview-appbar">
                    <div class="preview-brand-lockup"><img class="preview-brand-logo" src="/assets/brand/musabaqahub-app-icon.png" alt=""><span><strong>Musabaqa<em>Hub</em></strong><small><?= $escape->escapeText($translator->trans('home.preview.mobile_tagline')) ?></small></span></div>
                    <span class="preview-appbar-menu" aria-hidden="true"><i></i><i></i><i></i></span>
                </div>
                <div class="preview-screen-content">
                    <p class="preview-route"><?= $escape->escapeText($translator->trans('home.preview.route')) ?> <span aria-hidden="true">›</span> <strong><?= $escape->escapeText($translator->trans('home.preview.competition_short')) ?></strong></p>
                    <div class="preview-verified"><span aria-hidden="true">✓</span><div><strong><?= $escape->escapeText($translator->trans('home.preview.secure')) ?></strong><small><?= $escape->escapeText($translator->trans('home.preview.verified_note')) ?></small></div><b aria-hidden="true">›</b></div>
                    <div class="preview-banner">
                        <div><p><?= $escape->escapeText($translator->trans('home.preview.competition_name')) ?></p><small><?= $escape->escapeText($translator->trans('home.preview.competition_tagline')) ?></small></div>
                        <span class="preview-medallion" aria-hidden="true"><b><?= $escape->escapeText($translator->trans('home.preview.position_value')) ?></b><small><?= $escape->escapeText($translator->trans('home.preview.position')) ?></small></span>
                    </div>

                    <section class="preview-data-card preview-profile-card">
                        <div class="preview-avatar" aria-hidden="true"><span>AY</span><small>MH</small></div>
                        <div class="preview-profile-copy">
                            <h3><?= $escape->escapeText($translator->trans('home.preview.profile')) ?></h3>
                            <strong><?= $escape->escapeText($translator->trans('home.preview.participant_name')) ?></strong>
                            <dl>
                                <div><dt><?= $escape->escapeText($translator->trans('home.preview.participant_id')) ?></dt><dd>MH2026-0147</dd></div>
                                <div><dt><?= $escape->escapeText($translator->trans('home.preview.institution')) ?></dt><dd><?= $escape->escapeText($translator->trans('home.preview.institution_value')) ?></dd></div>
                                <div><dt><?= $escape->escapeText($translator->trans('home.preview.state')) ?></dt><dd><?= $escape->escapeText($translator->trans('home.preview.state_value')) ?></dd></div>
                            </dl>
                        </div>
                    </section>

                    <section class="preview-data-card preview-competition-card">
                        <h3><span aria-hidden="true">◆</span><?= $escape->escapeText($translator->trans('home.preview.details')) ?></h3>
                        <dl class="preview-facts">
                            <div><dt><?= $escape->escapeText($translator->trans('home.preview.category')) ?></dt><dd><?= $escape->escapeText($translator->trans('home.preview.category_value')) ?></dd></div>
                            <div><dt><?= $escape->escapeText($translator->trans('home.preview.year')) ?></dt><dd><?= $escape->escapeText($translator->trans('home.preview.year_value')) ?></dd></div>
                            <div><dt><?= $escape->escapeText($translator->trans('home.preview.date')) ?></dt><dd><?= $escape->escapeText($translator->trans('home.preview.date_value')) ?></dd></div>
                            <div><dt><?= $escape->escapeText($translator->trans('home.preview.venue')) ?></dt><dd><?= $escape->escapeText($translator->trans('home.preview.venue_value')) ?></dd></div>
                        </dl>
                    </section>

                    <div class="preview-result-grid">
                        <section class="preview-data-card preview-summary-card">
                            <h3><span aria-hidden="true">♕</span><?= $escape->escapeText($translator->trans('home.preview.summary')) ?></h3>
                            <div class="preview-position"><small><?= $escape->escapeText($translator->trans('home.preview.position')) ?></small><strong><?= $escape->escapeText($translator->trans('home.preview.position_value')) ?></strong><span>★</span></div>
                            <dl><div><dt><?= $escape->escapeText($translator->trans('home.preview.total_score')) ?></dt><dd>96.80 / 100</dd></div><div><dt><?= $escape->escapeText($translator->trans('home.preview.stage')) ?></dt><dd><?= $escape->escapeText($translator->trans('home.preview.stage_value')) ?></dd></div></dl>
                            <p><?= $escape->escapeText($translator->trans('home.preview.winner')) ?></p>
                        </section>
                        <section class="preview-data-card preview-score-card">
                            <h3><span aria-hidden="true">▥</span><?= $escape->escapeText($translator->trans('home.preview.score_breakdown')) ?></h3>
                            <dl><div><dt><?= $escape->escapeText($translator->trans('home.preview.recitation')) ?></dt><dd>98.0</dd></div><div><dt><?= $escape->escapeText($translator->trans('home.preview.tafseer')) ?></dt><dd>96.5</dd></div><div><dt><?= $escape->escapeText($translator->trans('home.preview.tajweed')) ?></dt><dd>96.0</dd></div></dl>
                            <p><span><?= $escape->escapeText($translator->trans('home.preview.total_score')) ?></span><strong>96.80</strong></p>
                        </section>
                    </div>

                    <div class="preview-record-footer"><span aria-hidden="true">✓</span><div><strong><?= $escape->escapeText($translator->trans('home.preview.certificate')) ?></strong><small><?= $escape->escapeText($translator->trans('home.preview.certificate_note')) ?></small></div><b aria-hidden="true">›</b></div>
                </div>
                <div class="preview-device-home" aria-hidden="true"></div>
            </div>
        </div>
    </div>
</section>

<section class="public-entry shell" aria-labelledby="public-entry-title">
    <div class="public-section-heading centered-heading public-entry-heading">
        <p class="public-kicker"><?= $escape->escapeText($translator->trans('home.entry.eyebrow')) ?></p>
        <h2 id="public-entry-title"><?= $escape->escapeText($translator->trans('home.entry.title')) ?></h2>
        <p><?= $escape->escapeText($translator->trans('home.entry.body')) ?></p>
    </div>
    <div class="public-entry-grid">
        <a class="entry-card" href="/search?lang=<?= $escape->escapeAttribute($locale) ?>"><span class="entry-icon" aria-hidden="true">⌕</span><div><h3><?= $escape->escapeText($translator->trans('home.entry.discover.title')) ?></h3><p><?= $escape->escapeText($translator->trans('home.entry.discover.body')) ?></p><strong><?= $escape->escapeText($translator->trans('home.entry.open')) ?> →</strong></div></a>
        <a class="entry-card" href="/statistics?lang=<?= $escape->escapeAttribute($locale) ?>"><span class="entry-icon" aria-hidden="true">▥</span><div><h3><?= $escape->escapeText($translator->trans('home.entry.results.title')) ?></h3><p><?= $escape->escapeText($translator->trans('home.entry.results.body')) ?></p><strong><?= $escape->escapeText($translator->trans('home.entry.open')) ?> →</strong></div></a>
        <a class="entry-card" href="/community?lang=<?= $escape->escapeAttribute($locale) ?>"><span class="entry-icon" aria-hidden="true">◖</span><div><h3><?= $escape->escapeText($translator->trans('home.entry.community.title')) ?></h3><p><?= $escape->escapeText($translator->trans('home.entry.community.body')) ?></p><strong><?= $escape->escapeText($translator->trans('home.entry.open')) ?> →</strong></div></a>
        <a class="entry-card" href="/quran?lang=<?= $escape->escapeAttribute($locale) ?>"><span class="entry-icon" aria-hidden="true">⌑</span><div><h3><?= $escape->escapeText($translator->trans('home.entry.quran.title')) ?></h3><p><?= $escape->escapeText($translator->trans('home.entry.quran.body')) ?></p><strong><?= $escape->escapeText($translator->trans('home.entry.open')) ?> →</strong></div></a>
    </div>
</section>

<section class="platform-story public-section">
    <div class="shell platform-story-grid">
        <div class="platform-story-visual">
            <div class="story-arch" aria-hidden="true"><img src="/assets/brand/musabaqahub-app-icon.png" alt=""><span></span></div>
            <blockquote><p><?= $escape->escapeText($translator->trans('home.verse')) ?></p><cite><?= $escape->escapeText($translator->trans('home.verse_reference')) ?></cite></blockquote>
        </div>
        <div class="platform-story-copy">
            <p class="public-kicker"><?= $escape->escapeText($translator->trans('home.platform.eyebrow')) ?></p>
            <h2><?= $escape->escapeText($translator->trans('home.platform.title')) ?></h2>
            <p class="section-lead"><?= $escape->escapeText($translator->trans('home.platform.body')) ?></p>
            <div class="platform-principles">
                <details class="platform-principle" open><summary><strong><?= $escape->escapeText($translator->trans('home.platform.integrity.title')) ?></strong></summary><p><?= $escape->escapeText($translator->trans('home.platform.integrity.body')) ?></p></details>
                <details class="platform-principle"><summary><strong><?= $escape->escapeText($translator->trans('home.platform.recognition.title')) ?></strong></summary><p><?= $escape->escapeText($translator->trans('home.platform.recognition.body')) ?></p></details>
                <details class="platform-principle"><summary><strong><?= $escape->escapeText($translator->trans('home.platform.access.title')) ?></strong></summary><p><?= $escape->escapeText($translator->trans('home.platform.access.body')) ?></p></details>
            </div>
            <a class="text-link" href="/system/about?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('home.platform.action')) ?> <span aria-hidden="true">→</span></a>
        </div>
    </div>
</section>

<section class="competition-journey public-section shell" aria-labelledby="journey-title">
    <div class="public-section-heading centered-heading"><p class="public-kicker"><?= $escape->escapeText($translator->trans('home.journey.eyebrow')) ?></p><h2 id="journey-title"><?= $escape->escapeText($translator->trans('home.journey.title')) ?></h2><p><?= $escape->escapeText($translator->trans('home.journey.body')) ?></p></div>
    <ol class="journey-steps">
        <li><span>01</span><h3><?= $escape->escapeText($translator->trans('home.journey.organize.title')) ?></h3><p><?= $escape->escapeText($translator->trans('home.journey.organize.body')) ?></p></li>
        <li><span>02</span><h3><?= $escape->escapeText($translator->trans('home.journey.participate.title')) ?></h3><p><?= $escape->escapeText($translator->trans('home.journey.participate.body')) ?></p></li>
        <li><span>03</span><h3><?= $escape->escapeText($translator->trans('home.journey.adjudicate.title')) ?></h3><p><?= $escape->escapeText($translator->trans('home.journey.adjudicate.body')) ?></p></li>
        <li><span>04</span><h3><?= $escape->escapeText($translator->trans('home.journey.recognize.title')) ?></h3><p><?= $escape->escapeText($translator->trans('home.journey.recognize.body')) ?></p></li>
    </ol>
</section>

<section class="audience-section public-section">
    <div class="shell">
        <div class="public-section-heading public-section-heading-row"><div><p class="public-kicker"><?= $escape->escapeText($translator->trans('home.audience.eyebrow')) ?></p><h2><?= $escape->escapeText($translator->trans('home.audience.title')) ?></h2></div><p><?= $escape->escapeText($translator->trans('home.audience.body')) ?></p></div>
        <div class="audience-grid">
            <article><span class="audience-number">01</span><h3><?= $escape->escapeText($translator->trans('home.audience.participants.title')) ?></h3><p><?= $escape->escapeText($translator->trans('home.audience.participants.body')) ?></p><a href="/search?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('home.audience.participants.action')) ?> →</a></article>
            <article><span class="audience-number">02</span><h3><?= $escape->escapeText($translator->trans('home.audience.organizers.title')) ?></h3><p><?= $escape->escapeText($translator->trans('home.audience.organizers.body')) ?></p><a href="/register?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('home.audience.organizers.action')) ?> →</a></article>
            <article><span class="audience-number">03</span><h3><?= $escape->escapeText($translator->trans('home.audience.community.title')) ?></h3><p><?= $escape->escapeText($translator->trans('home.audience.community.body')) ?></p><a href="/community?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('home.audience.community.action')) ?> →</a></article>
        </div>
    </div>
</section>

<section class="public-trust-section public-section shell">
    <div class="trust-panel">
        <div><p class="public-kicker"><?= $escape->escapeText($translator->trans('home.governance.eyebrow')) ?></p><h2><?= $escape->escapeText($translator->trans('home.governance.title')) ?></h2><p><?= $escape->escapeText($translator->trans('home.governance.body')) ?></p></div>
        <ul>
            <li><span aria-hidden="true">✓</span><div><strong><?= $escape->escapeText($translator->trans('home.governance.verified.title')) ?></strong><small><?= $escape->escapeText($translator->trans('home.governance.verified.body')) ?></small></div></li>
            <li><span aria-hidden="true">✓</span><div><strong><?= $escape->escapeText($translator->trans('home.governance.safety.title')) ?></strong><small><?= $escape->escapeText($translator->trans('home.governance.safety.body')) ?></small></div></li>
            <li><span aria-hidden="true">✓</span><div><strong><?= $escape->escapeText($translator->trans('home.governance.resilience.title')) ?></strong><small><?= $escape->escapeText($translator->trans('home.governance.resilience.body')) ?></small></div></li>
        </ul>
    </div>
</section>

<section class="public-cta">
    <div class="shell public-cta-inner">
        <div><p class="public-kicker"><?= $escape->escapeText($translator->trans('home.cta.eyebrow')) ?></p><h2><?= $escape->escapeText($translator->trans('home.cta.title')) ?></h2><p><?= $escape->escapeText($translator->trans('home.cta.body')) ?></p></div>
        <div class="actions"><a class="button button-gold button-large" href="/register?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('home.cta.primary')) ?></a><a class="button button-on-dark button-large" href="/search?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('home.cta.secondary')) ?></a></div>
    </div>
</section>
