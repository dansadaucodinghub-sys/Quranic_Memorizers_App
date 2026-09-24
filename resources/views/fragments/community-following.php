<?php

declare(strict_types=1);

$relationships = $view->list('relationships');
?>
<section data-qmdb-fragment-root aria-label="<?= $escape->escapeAttribute($translator->trans('community.social.following_title')) ?>">
    <?php if ($view->boolean('saved')) : ?>
        <p role="status"><?= $escape->escapeText($translator->trans('community.social.saved')) ?></p>
    <?php endif; ?>
    <?php if ($view->string('error') !== '') : ?>
        <p role="alert"><?= $escape->escapeText($translator->trans($view->string('error'))) ?></p>
    <?php endif; ?>
    <?php if ($relationships === []) : ?>
        <p><?= $escape->escapeText($translator->trans('community.social.following_empty')) ?></p>
    <?php else : ?>
        <ol class="community-creator-list">
            <?php foreach ($relationships as $item) : ?>
                <li><article>
                    <h2><bdi><?= $escape->escapeText($item['alias']) ?></bdi></h2>
                    <p><?= $escape->escapeText($translator->trans('community.social.follower_status')) ?>: <?= $escape->escapeText($item['status']) ?></p>
                    <form method="post" data-qmdb-progressive-form action="/account/community/social/<?= $escape->escapeAttribute($item['profile_id']) ?>/unfollow">
                        <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
                        <input type="hidden" name="submission_id" data-qmdb-idempotency-key value="<?= $escape->escapeAttribute($item['follow_submission']) ?>">
                        <input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute((string) $item['version']) ?>">
                        <button type="submit"><?= $escape->escapeText($translator->trans('community.social.unfollow')) ?></button>
                    </form>
                </article></li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
