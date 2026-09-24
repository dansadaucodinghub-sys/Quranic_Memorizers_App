<?php

declare(strict_types=1);

$queue = $view->list('queue');
?>
<section data-qmdb-fragment-root aria-label="<?= $escape->escapeAttribute($translator->trans('community.review.title')) ?>">
    <p><?= $escape->escapeText($translator->trans('community.review.independent')) ?></p>
    <?php if ($view->boolean('published')) : ?>
        <p role="status"><?= $escape->escapeText($translator->trans('community.review.published')) ?></p>
    <?php endif; ?>
    <?php if ($view->string('error') !== '') : ?>
        <p role="alert"><?= $escape->escapeText($translator->trans($view->string('error'))) ?></p>
    <?php endif; ?>
    <?php if ($queue === []) : ?>
        <p><?= $escape->escapeText($translator->trans('community.review.empty')) ?></p>
    <?php else : ?>
        <ol class="community-creator-list">
            <?php foreach ($queue as $item) : ?>
                <li><article>
                    <h2><bdi><?= $escape->escapeText($item['public_id']) ?></bdi></h2>
                    <p dir="<?= $item['language'] === 'ar' ? 'rtl' : 'ltr' ?>"><?= $escape->escapeText($item['caption']) ?></p>
                    <p><?= $escape->escapeText($translator->trans('community.clip.passage')) ?>: <bdi><?= $escape->escapeText($item['surah'] . ':' . $item['start'] . '–' . $item['end']) ?></bdi></p>
                    <?php if ($item['supersedes_public_id'] !== null) : ?>
                        <p><?= $escape->escapeText($translator->trans('community.creator.replaces')) ?>: <a href="/clips/<?= $escape->escapeAttribute($item['supersedes_public_id']) ?>"><bdi><?= $escape->escapeText($item['supersedes_public_id']) ?></bdi></a></p>
                    <?php endif; ?>
                    <p><a href="/workspace/media/<?= $escape->escapeAttribute($item['asset_id']) ?>/content"><?= $escape->escapeText($translator->trans('community.review.media')) ?></a></p>
                    <p><?= $escape->escapeText($translator->trans('community.review.media_permission')) ?></p>
                    <form method="post" data-qmdb-progressive-form action="/workspace/community/review/<?= $escape->escapeAttribute($item['public_id']) ?>/publish">
                        <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
                        <input type="hidden" name="submission_id" data-qmdb-idempotency-key value="<?= $escape->escapeAttribute($item['submission_id']) ?>">
                        <input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute((string) $item['version']) ?>">
                        <button type="submit"><?= $escape->escapeText($translator->trans('community.review.publish')) ?></button>
                    </form>
                </article></li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
