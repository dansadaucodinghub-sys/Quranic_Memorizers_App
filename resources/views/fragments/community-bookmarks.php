<?php

declare(strict_types=1);

$clips = $view->list('clips');
?>
<section data-qmdb-fragment-root aria-label="<?= $escape->escapeAttribute($translator->trans('community.bookmarks.title')) ?>">
    <p><?= $escape->escapeText($translator->trans('community.bookmarks.notice')) ?></p>
    <?php if ($clips === []) : ?>
        <p><?= $escape->escapeText($translator->trans('community.bookmarks.empty')) ?></p>
    <?php else : ?>
        <ol class="community-feed-list">
            <?php foreach ($clips as $clip) : ?>
                <li><article class="community-feed-item">
                    <h2><a href="/clips/<?= $escape->escapeAttribute($clip['clip_id']) ?>"><?= $escape->escapeText($translator->trans('community.feed.open')) ?></a></h2>
                    <p><bdi><?= $escape->escapeText($clip['alias']) ?></bdi></p>
                    <p dir="<?= $clip['language'] === 'ar' ? 'rtl' : 'ltr' ?>"><?= $escape->escapeText($clip['caption']) ?></p>
                </article></li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
