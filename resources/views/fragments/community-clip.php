<?php

declare(strict_types=1);

$clip = $view->array('clip');
$kind = $clip['language'] === 'ar' ? 'rtl' : 'ltr';
?>
<section data-qmdb-fragment-root aria-label="<?= $escape->escapeAttribute($translator->trans('community.clip.title')) ?>">
    <h2><a href="/community/profiles/<?= $escape->escapeAttribute($clip['profile_id']) ?>"><bdi><?= $escape->escapeText($clip['alias']) ?></bdi></a></h2>
    <p><?= $escape->escapeText($translator->trans('community.clip.passage')) ?>:
        <bdi><?= $escape->escapeText((string) $clip['surah']) ?>:<?= $escape->escapeText((string) $clip['start']) ?>–<?= $escape->escapeText((string) $clip['end']) ?></bdi>
    </p>
    <p dir="<?= $kind ?>"><?= $escape->escapeText($clip['caption']) ?></p>
    <?php if ($clip['media_kind'] === 'VIDEO') : ?>
        <video controls preload="none" playsinline aria-label="<?= $escape->escapeAttribute($translator->trans('community.clip.playback')) ?>">
            <source src="<?= $escape->escapeAttribute($clip['media_url']) ?>" type="video/mp4">
            <?= $escape->escapeText($translator->trans('community.clip.no_media')) ?>
        </video>
    <?php else : ?>
        <audio controls preload="none" aria-label="<?= $escape->escapeAttribute($translator->trans('community.clip.playback')) ?>">
            <source src="<?= $escape->escapeAttribute($clip['media_url']) ?>" type="audio/mpeg">
            <?= $escape->escapeText($translator->trans('community.clip.no_media')) ?>
        </audio>
    <?php endif; ?>
    <p><a href="/quran/surahs/<?= $escape->escapeAttribute((string) $clip['surah']) ?>"><?= $escape->escapeText($translator->trans('community.clip.reference')) ?></a></p>
    <?php if ($view->boolean('saved')) : ?>
        <p role="status"><?= $escape->escapeText($translator->trans('community.engagement.saved')) ?></p>
    <?php endif; ?>
    <?php if ($view->boolean('authenticated')) : ?>
        <?php $state = $view->array('interaction_state'); ?>
        <?php foreach (['reaction', 'bookmark'] as $kind) : ?>
            <?php $active = $state[$kind]['active'] ?? false; ?>
            <form method="post" data-qmdb-progressive-form action="/clips/<?= $escape->escapeAttribute($clip['clip_id']) ?>/interactions/<?= $escape->escapeAttribute($kind) ?>">
                <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
                <input type="hidden" name="submission_id" data-qmdb-idempotency-key value="<?= $escape->escapeAttribute($view->string($kind . '_submission')) ?>">
                <input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute((string) ($state[$kind]['version'] ?? 0)) ?>">
                <input type="hidden" name="action" value="<?= $active ? 'REMOVE' : 'ADD' ?>">
                <button type="submit"><?= $escape->escapeText($translator->trans('community.engagement.' . $kind . ($active ? '.remove' : '.add'))) ?></button>
            </form>
        <?php endforeach; ?>
    <?php endif; ?>
    <p><a href="/clips/<?= $escape->escapeAttribute($clip['clip_id']) ?>/comments"><?= $escape->escapeText($translator->trans('community.comments.title')) ?></a></p>
    <p><a href="/clips/<?= $escape->escapeAttribute($clip['clip_id']) ?>/report"><?= $escape->escapeText($translator->trans('community.report.title')) ?></a></p>
</section>
