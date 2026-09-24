<?php

declare(strict_types=1);

$relationships = $view->list('relationships');
?>
<section data-qmdb-fragment-root aria-label="<?= $escape->escapeAttribute($translator->trans('community.social.safety_title')) ?>">
    <?php if ($view->boolean('saved')) : ?>
        <p role="status"><?= $escape->escapeText($translator->trans('community.social.saved')) ?></p>
    <?php endif; ?>
    <?php if ($view->string('error') !== '') : ?>
        <p role="alert"><?= $escape->escapeText($translator->trans($view->string('error'))) ?></p>
    <?php endif; ?>
    <p><?= $escape->escapeText($translator->trans('community.social.safety_notice')) ?></p>
    <?php if ($relationships === []) : ?>
        <p><?= $escape->escapeText($translator->trans('community.social.safety_empty')) ?></p>
    <?php else : ?>
        <ol class="community-creator-list">
            <?php foreach ($relationships as $item) : ?>
                <li><article>
                    <h2><bdi><?= $escape->escapeText($item['alias']) ?></bdi></h2>
                    <?php if ($item['blocked']) : ?>
                        <form method="post" data-qmdb-progressive-form action="/account/community/social/<?= $escape->escapeAttribute($item['profile_id']) ?>/unblock">
                            <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
                            <input type="hidden" name="submission_id" data-qmdb-idempotency-key value="<?= $escape->escapeAttribute($item['block_submission']) ?>">
                            <input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute((string) $item['block_version']) ?>">
                            <button type="submit"><?= $escape->escapeText($translator->trans('community.social.unblock')) ?></button>
                        </form>
                    <?php endif; ?>
                    <?php if ($item['muted']) : ?>
                        <form method="post" data-qmdb-progressive-form action="/account/community/social/<?= $escape->escapeAttribute($item['profile_id']) ?>/unmute">
                            <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
                            <input type="hidden" name="submission_id" data-qmdb-idempotency-key value="<?= $escape->escapeAttribute($item['mute_submission']) ?>">
                            <input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute((string) $item['mute_version']) ?>">
                            <button type="submit"><?= $escape->escapeText($translator->trans('community.social.unmute')) ?></button>
                        </form>
                    <?php endif; ?>
                </article></li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
