<?php

declare(strict_types=1);

$profileId = $view->string('profile_id');
?>
<section data-qmdb-fragment-root aria-label="<?= $escape->escapeAttribute($translator->trans('community.profile.title')) ?>">
    <p><?= $escape->escapeText($translator->trans('community.profile.private_notice')) ?></p>
    <?php if ($view->boolean('saved')) : ?>
        <p role="status"><?= $escape->escapeText($translator->trans('community.profile.saved')) ?></p>
    <?php endif; ?>
    <?php if ($view->string('error') !== '') : ?>
        <p role="alert"><?= $escape->escapeText($translator->trans($view->string('error'))) ?></p>
    <?php endif; ?>
    <form method="post" data-qmdb-progressive-form action="/account/community/profile/update">
        <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
        <input type="hidden" name="submission_id" data-qmdb-idempotency-key value="<?= $escape->escapeAttribute($view->string('submission_id')) ?>">
        <input type="hidden" name="profile_id" value="<?= $escape->escapeAttribute($profileId) ?>">
        <input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute((string) $view->integer('expected_version')) ?>">
        <p><label for="community-profile-alias"><?= $escape->escapeText($translator->trans('community.profile.alias')) ?></label>
            <input id="community-profile-alias" name="alias" type="text" maxlength="80" required autocomplete="nickname" value="<?= $escape->escapeAttribute($view->string('alias')) ?>"></p>
        <?php if ($profileId !== '') : ?>
            <p><label for="community-profile-visibility"><?= $escape->escapeText($translator->trans('community.profile.visibility')) ?></label>
                <select id="community-profile-visibility" name="visibility">
                    <option value="PRIVATE"<?= $view->string('visibility') === 'PRIVATE' ? ' selected' : '' ?>><?= $escape->escapeText($translator->trans('community.profile.private')) ?></option>
                    <option value="PUBLIC"<?= $view->string('visibility') === 'PUBLIC' ? ' selected' : '' ?>><?= $escape->escapeText($translator->trans('community.profile.public')) ?></option>
                </select></p>
        <?php else : ?>
            <input type="hidden" name="visibility" value="PRIVATE">
        <?php endif; ?>
        <button type="submit"><?= $escape->escapeText($translator->trans('community.profile.save')) ?></button>
    </form>
    <?php if ($profileId !== '' && $view->string('visibility') === 'PUBLIC') : ?>
        <p><a href="/community/profiles/<?= $escape->escapeAttribute($profileId) ?>"><?= $escape->escapeText($translator->trans('community.profile.view_public')) ?></a></p>
    <?php endif; ?>
</section>
