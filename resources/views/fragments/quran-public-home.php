<?php declare(strict_types=1); $summary = $view->array('summary'); ?>
<section data-qmdb-fragment-root aria-live="polite">
    <?php if ($summary === []): ?>
        <p><?= $escape->escapeText($translator->trans('quran.public.preparing')) ?></p>
    <?php else: ?>
        <dl class="facts">
            <div><dt><?= $escape->escapeText($translator->trans('quran.public.release')) ?></dt><dd><?= $escape->escapeText((string) $summary['release_code']) ?> <?= $escape->escapeText((string) $summary['release_version']) ?></dd></div>
            <div><dt><?= $escape->escapeText($translator->trans('quran.public.surah_count')) ?></dt><dd><?= $escape->escapeText((string) $summary['surah_count']) ?></dd></div>
            <div><dt><?= $escape->escapeText($translator->trans('quran.public.ayah_count')) ?></dt><dd><?= $escape->escapeText((string) $summary['ayah_count']) ?></dd></div>
        </dl>
    <?php endif; ?>
    <p class="field-hint"><?= $escape->escapeText($translator->trans('quran.public.source')) ?></p>
</section>
