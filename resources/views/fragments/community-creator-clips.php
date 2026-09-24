<?php

declare(strict_types=1);

$clips = $view->list('clips');
$media = $view->list('media');
$passages = $view->list('passages');
$token = $view->string('csrf_token');
?>
<section data-qmdb-fragment-root aria-label="<?= $escape->escapeAttribute($translator->trans('community.creator.title')) ?>">
    <?php if ($view->boolean('saved')) : ?>
        <p role="status"><?= $escape->escapeText($translator->trans('community.creator.saved')) ?></p>
    <?php endif; ?>
    <?php if ($view->string('error') !== '') : ?>
        <p role="alert"><?= $escape->escapeText($translator->trans($view->string('error'))) ?></p>
    <?php endif; ?>
    <p><?= $escape->escapeText($translator->trans('community.creator.p9_notice')) ?></p>
    <?php if ($media === [] || $passages === []) : ?>
        <p><?= $escape->escapeText($translator->trans('community.creator.prerequisites')) ?></p>
    <?php else : ?>
        <form method="post" data-qmdb-progressive-form action="/workspace/community/clips" class="community-creator-form">
            <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($token) ?>">
            <input type="hidden" name="submission_id" data-qmdb-idempotency-key value="<?= $escape->escapeAttribute($view->string('submission_id')) ?>">
            <p><label for="clip-media"><?= $escape->escapeText($translator->trans('community.creator.media')) ?></label>
                <select id="clip-media" name="media" required>
                    <?php foreach ($media as $item) : ?>
                        <option value="<?= $escape->escapeAttribute($item['asset_id'] . '|' . $item['variant_id']) ?>"><?= $escape->escapeText($item['media_kind'] . ' · ' . $item['asset_id']) ?></option>
                    <?php endforeach; ?>
                </select></p>
            <p><label for="clip-surah"><?= $escape->escapeText($translator->trans('community.creator.surah')) ?></label>
                <select id="clip-surah" name="surah_number" required>
                    <?php foreach ($passages as $passage) : ?>
                        <option value="<?= $escape->escapeAttribute((string) $passage['surah_number']) ?>"><?= $escape->escapeText($passage['surah_number'] . ' · ' . $passage['arabic_name'] . ' / ' . $passage['english_name'] . ' (' . $passage['ayah_count'] . ')') ?></option>
                    <?php endforeach; ?>
                </select></p>
            <input type="hidden" name="release_id" value="<?= $escape->escapeAttribute($passages[0]['release_id']) ?>">
            <p><label for="clip-start"><?= $escape->escapeText($translator->trans('community.creator.start')) ?></label>
                <input id="clip-start" name="start_ayah" type="number" min="1" max="286" required></p>
            <p><label for="clip-end"><?= $escape->escapeText($translator->trans('community.creator.end')) ?></label>
                <input id="clip-end" name="end_ayah" type="number" min="1" max="286" required></p>
            <p><label for="clip-caption"><?= $escape->escapeText($translator->trans('community.creator.caption')) ?></label>
                <textarea id="clip-caption" name="caption" maxlength="2000" rows="4"></textarea></p>
            <p><label for="clip-language"><?= $escape->escapeText($translator->trans('community.creator.language')) ?></label>
                <select id="clip-language" name="language"><option value="ar">العربية</option><option value="en">English</option></select></p>
            <p><label for="clip-supersedes"><?= $escape->escapeText($translator->trans('community.creator.supersedes')) ?></label>
                <select id="clip-supersedes" name="supersedes_clip_id">
                    <option value=""><?= $escape->escapeText($translator->trans('community.creator.supersedes.none')) ?></option>
                    <?php foreach ($clips as $candidate) : ?>
                        <?php if ($candidate['status'] === 'PUBLISHED') : ?>
                            <option value="<?= $escape->escapeAttribute($candidate['public_id']) ?>"><?= $escape->escapeText($candidate['caption'] !== '' ? $candidate['caption'] : $candidate['public_id']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select></p>
            <button type="submit"><?= $escape->escapeText($translator->trans('community.creator.create')) ?></button>
        </form>
    <?php endif; ?>
    <h2><?= $escape->escapeText($translator->trans('community.creator.own')) ?></h2>
    <?php if ($clips === []) : ?>
        <p><?= $escape->escapeText($translator->trans('community.creator.empty')) ?></p>
    <?php else : ?>
        <ol class="community-creator-list">
            <?php foreach ($clips as $clip) : ?>
                <li>
                    <article>
                        <h3><bdi><?= $escape->escapeText($clip['public_id']) ?></bdi></h3>
                        <p><?= $escape->escapeText($translator->trans('community.creator.status')) ?>: <?= $escape->escapeText($clip['status']) ?></p>
                        <?php if ($clip['supersedes_public_id'] !== null) : ?>
                            <p><?= $escape->escapeText($translator->trans('community.creator.replaces')) ?>: <bdi><?= $escape->escapeText($clip['supersedes_public_id']) ?></bdi></p>
                        <?php endif; ?>
                        <p dir="<?= $clip['language'] === 'ar' ? 'rtl' : 'ltr' ?>"><?= $escape->escapeText($clip['caption']) ?></p>
                        <?php if ($clip['status'] === 'DRAFT') : ?>
                            <form method="post" data-qmdb-progressive-form action="/workspace/community/clips/<?= $escape->escapeAttribute($clip['public_id']) ?>/update">
                                <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($token) ?>">
                                <input type="hidden" name="submission_id" data-qmdb-idempotency-key value="<?= $escape->escapeAttribute($clip['submissions']['update']) ?>">
                                <input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute((string) $clip['version']) ?>">
                                <p><label for="clip-caption-<?= $escape->escapeAttribute($clip['public_id']) ?>"><?= $escape->escapeText($translator->trans('community.creator.caption')) ?></label>
                                    <textarea id="clip-caption-<?= $escape->escapeAttribute($clip['public_id']) ?>" name="caption" maxlength="2000" rows="3"><?= $escape->escapeText($clip['caption']) ?></textarea></p>
                                <p><label for="clip-language-<?= $escape->escapeAttribute($clip['public_id']) ?>"><?= $escape->escapeText($translator->trans('community.creator.language')) ?></label>
                                    <select id="clip-language-<?= $escape->escapeAttribute($clip['public_id']) ?>" name="language">
                                        <option value="ar"<?= $clip['language'] === 'ar' ? ' selected' : '' ?>>العربية</option>
                                        <option value="en"<?= $clip['language'] === 'en' ? ' selected' : '' ?>>English</option>
                                    </select></p>
                                <p><label for="clip-comments-<?= $escape->escapeAttribute($clip['public_id']) ?>"><?= $escape->escapeText($translator->trans('community.creator.comments')) ?></label>
                                    <select id="clip-comments-<?= $escape->escapeAttribute($clip['public_id']) ?>" name="comment_policy">
                                        <?php foreach (['DISABLED', 'REVIEW', 'ENABLED'] as $policy) : ?>
                                            <option value="<?= $escape->escapeAttribute($policy) ?>"<?= $clip['comment_policy'] === $policy ? ' selected' : '' ?>><?= $escape->escapeText($translator->trans('community.creator.comments.' . strtolower($policy))) ?></option>
                                        <?php endforeach; ?>
                                    </select></p>
                                <button type="submit"><?= $escape->escapeText($translator->trans('community.creator.update')) ?></button>
                            </form>
                        <?php endif; ?>
                        <?php foreach (['submit', 'hide', 'remove', 'archive'] as $action) : ?>
                            <?php if (
                            ($action === 'submit' && $clip['status'] !== 'DRAFT')
                                || ($action === 'hide' && $clip['status'] !== 'PUBLISHED')
                                || ($action === 'remove' && !in_array($clip['status'], ['PUBLISHED', 'HIDDEN'], true))
                                || ($action === 'archive' && !in_array($clip['status'], ['DRAFT', 'HIDDEN', 'REMOVED'], true))
) {
                                continue;
                            } ?>
                            <form method="post" data-qmdb-progressive-form action="/workspace/community/clips/<?= $escape->escapeAttribute($clip['public_id']) ?>/<?= $escape->escapeAttribute($action) ?>">
                                <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($token) ?>">
                                <input type="hidden" name="submission_id" data-qmdb-idempotency-key value="<?= $escape->escapeAttribute($clip['submissions'][$action]) ?>">
                                <input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute((string) $clip['version']) ?>">
                                <button type="submit"><?= $escape->escapeText($translator->trans('community.creator.' . $action)) ?></button>
                            </form>
                        <?php endforeach; ?>
                        <?php if ($clip['status'] === 'PUBLISHED') : ?>
                            <a href="/clips/<?= $escape->escapeAttribute($clip['public_id']) ?>"><?= $escape->escapeText($translator->trans('community.creator.public')) ?></a>
                        <?php endif; ?>
                    </article>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
