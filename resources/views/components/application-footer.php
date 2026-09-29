<?php

declare(strict_types=1);

$locale = $translator->locale()->value();
?>
<footer class="site-footer">
    <div class="shell footer-grid">
        <div class="footer-introduction">
            <a class="footer-brand" href="/?lang=<?= $escape->escapeAttribute($locale) ?>">
                <img src="/assets/brand/musabaqahub-app-icon.png" width="58" height="58" alt="">
                <span><strong>MusabaqaHub</strong><small><?= $escape->escapeText($translator->trans('app.tagline')) ?></small></span>
            </a>
            <p><?= $escape->escapeText($translator->trans('footer.scope')) ?></p>
            <p class="footer-promise"><?= $escape->escapeText($translator->trans('brand.promise')) ?></p>
        </div>
        <nav class="footer-navigation" aria-label="<?= $escape->escapeAttribute($translator->trans('footer.platform')) ?>">
            <h2><?= $escape->escapeText($translator->trans('footer.platform')) ?></h2>
            <a href="/search?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('nav.discover')) ?></a>
            <a href="/statistics?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('nav.results')) ?></a>
            <a href="/community?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('nav.community')) ?></a>
            <a href="/quran?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('nav.quran')) ?></a>
        </nav>
        <nav class="footer-navigation" aria-label="<?= $escape->escapeAttribute($translator->trans('footer.resources')) ?>">
            <h2><?= $escape->escapeText($translator->trans('footer.resources')) ?></h2>
            <a href="/locations/nigeria?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('footer.locations')) ?></a>
            <a href="/system/about?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('nav.about')) ?></a>
            <a href="/system/status?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('nav.status')) ?></a>
        </nav>
        <nav class="footer-navigation" aria-label="<?= $escape->escapeAttribute($translator->trans('footer.account')) ?>">
            <h2><?= $escape->escapeText($translator->trans('footer.account')) ?></h2>
            <a href="/login?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('nav.sign_in')) ?></a>
            <a href="/register?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('nav.register')) ?></a>
            <a href="/forgot-password?lang=<?= $escape->escapeAttribute($locale) ?>"><?= $escape->escapeText($translator->trans('footer.recover_account')) ?></a>
        </nav>
    </div>
    <div class="shell footer-bottom"><span>© <?= date('Y') ?> MusabaqaHub</span><span><?= $escape->escapeText($translator->trans('footer.stewardship')) ?></span></div>
</footer>
