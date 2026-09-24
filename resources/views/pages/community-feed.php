<?php

declare(strict_types=1);

?>
<article class="shell prose-page" data-qmdb-community-feed>
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('community.clip.eyebrow')) ?></p>
    <h1><?= $escape->escapeText($translator->trans($view->string('title_key'))) ?></h1>
    <?php if ($view->string('profile_alias') !== '') : ?>
        <p><bdi><?= $escape->escapeText($view->string('profile_alias')) ?></bdi></p>
    <?php endif; ?>
    <?php $socialState = $view->array('social_state'); ?>
    <?php if ($view->boolean('social_saved')) : ?>
        <p role="status"><?= $escape->escapeText($translator->trans('community.social.saved')) ?></p>
    <?php endif; ?>
    <?php if ($socialState !== [] && !$socialState['own_profile']) : ?>
        <?php $ids = $view->array('social_submission_ids'); ?>
        <?php $followAction = in_array($socialState['follow_status'], ['ACTIVE', 'PENDING'], true) ? 'unfollow' : 'follow'; ?>
        <div class="community-social-actions" aria-label="<?= $escape->escapeAttribute($translator->trans('community.social.actions')) ?>">
            <?php foreach ([$followAction, 'block', $socialState['mute_active'] ? 'unmute' : 'mute'] as $action) : ?>
                <?php $category = in_array($action, ['follow', 'unfollow'], true) ? 'follow'
                    : (in_array($action, ['mute', 'unmute'], true) ? 'mute' : 'block'); ?>
                <?php $version = match ($category) {
                    'follow' => $socialState['follow_version'],
                    'block' => $socialState['block_version'],
                    'mute' => $socialState['mute_version'],
                }; ?>
                <form method="post" action="/account/community/social/<?= $escape->escapeAttribute($view->string('profile_id')) ?>/<?= $escape->escapeAttribute($action) ?>">
                    <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('social_csrf_token')) ?>">
                    <input type="hidden" name="submission_id" value="<?= $escape->escapeAttribute($ids[$category]) ?>">
                    <input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute((string) $version) ?>">
                    <button type="submit"><?= $escape->escapeText($translator->trans('community.social.' . $action)) ?></button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?= $renderer->render('fragments.community-feed', $view, $translator)->trustedHtml() ?>
</article>
