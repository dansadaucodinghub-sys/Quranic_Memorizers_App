<?php

declare(strict_types=1);

$comments = $view->list('comments');
$clipId = $view->string('clip_id');
$csrfToken = $view->string('csrf_token');
?>
<section data-qmdb-fragment-root aria-label="<?= $escape->escapeAttribute($translator->trans('community.comments.title')) ?>">
    <p><a href="/clips/<?= $escape->escapeAttribute($clipId) ?>"><?= $escape->escapeText($translator->trans('community.comments.back')) ?></a></p>
    <?php if ($view->boolean('saved')) : ?>
        <p role="status"><?= $escape->escapeText($translator->trans('community.comments.saved')) ?></p>
    <?php endif; ?>
    <?php if ($view->string('error') !== '') : ?>
        <p role="alert"><?= $escape->escapeText($translator->trans($view->string('error'))) ?></p>
    <?php endif; ?>
    <?php if ($comments === []) : ?>
        <p><?= $escape->escapeText($translator->trans('community.comments.empty')) ?></p>
    <?php else : ?>
        <ol class="community-creator-list">
            <?php foreach ($comments as $comment) : ?>
                <li id="comment-<?= $escape->escapeAttribute($comment['public_id']) ?>">
                    <article>
                        <?php if ($comment['parent_id'] !== null) : ?>
                            <p><?= $escape->escapeText($translator->trans('community.comments.reply_marker')) ?></p>
                        <?php endif; ?>
                        <?php if ($comment['status'] === 'MODERATION_HELD') : ?>
                            <p><?= $escape->escapeText($translator->trans('community.comments.pending')) ?></p>
                        <?php endif; ?>
                        <p><?= $escape->escapeText($comment['body']) ?></p>
                        <p><time datetime="<?= $escape->escapeAttribute(str_replace(' ', 'T', $comment['created_at']) . 'Z') ?>"><?= $escape->escapeText($comment['created_at']) ?> UTC</time></p>
                        <?php if ($view->boolean('authenticated') && $comment['parent_id'] === null && $comment['status'] !== 'MODERATION_HELD') : ?>
                            <form method="post" data-qmdb-progressive-form action="/clips/<?= $escape->escapeAttribute($clipId) ?>/comments/<?= $escape->escapeAttribute($comment['public_id']) ?>/reply">
                                <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($csrfToken) ?>">
                                <input type="hidden" name="submission_id" data-qmdb-idempotency-key value="<?= $escape->escapeAttribute($comment['reply_submission']) ?>">
                                <label for="reply-<?= $escape->escapeAttribute($comment['public_id']) ?>"><?= $escape->escapeText($translator->trans('community.comments.reply')) ?></label>
                                <textarea id="reply-<?= $escape->escapeAttribute($comment['public_id']) ?>" name="body" maxlength="2000" required></textarea>
                                <button type="submit"><?= $escape->escapeText($translator->trans('community.comments.reply')) ?></button>
                            </form>
                        <?php endif; ?>
                        <?php if ($comment['is_mine']) : ?>
                            <form method="post" data-qmdb-progressive-form action="/clips/<?= $escape->escapeAttribute($clipId) ?>/comments/<?= $escape->escapeAttribute($comment['public_id']) ?>/edit">
                                <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($csrfToken) ?>">
                                <input type="hidden" name="submission_id" data-qmdb-idempotency-key value="<?= $escape->escapeAttribute($comment['edit_submission']) ?>">
                                <input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute((string) $comment['version']) ?>">
                                <label for="edit-<?= $escape->escapeAttribute($comment['public_id']) ?>"><?= $escape->escapeText($translator->trans('community.comments.edit')) ?></label>
                                <textarea id="edit-<?= $escape->escapeAttribute($comment['public_id']) ?>" name="body" maxlength="2000" required><?= $escape->escapeText($comment['body']) ?></textarea>
                                <button type="submit"><?= $escape->escapeText($translator->trans('community.comments.edit')) ?></button>
                            </form>
                            <form method="post" data-qmdb-progressive-form action="/clips/<?= $escape->escapeAttribute($clipId) ?>/comments/<?= $escape->escapeAttribute($comment['public_id']) ?>/remove">
                                <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($csrfToken) ?>">
                                <input type="hidden" name="submission_id" data-qmdb-idempotency-key value="<?= $escape->escapeAttribute($comment['remove_submission']) ?>">
                                <input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute((string) $comment['version']) ?>">
                                <button type="submit"><?= $escape->escapeText($translator->trans('community.comments.remove')) ?></button>
                            </form>
                        <?php endif; ?>
                    </article>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <?php if ($view->boolean('authenticated') && $view->string('comment_policy') !== 'DISABLED') : ?>
        <form method="post" data-qmdb-progressive-form action="/clips/<?= $escape->escapeAttribute($clipId) ?>/comments">
            <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($csrfToken) ?>">
            <input type="hidden" name="submission_id" data-qmdb-idempotency-key value="<?= $escape->escapeAttribute($view->string('submission_id')) ?>">
            <label for="community-comment-body"><?= $escape->escapeText($translator->trans('community.comments.write')) ?></label>
            <textarea id="community-comment-body" name="body" maxlength="2000" required></textarea>
            <button type="submit"><?= $escape->escapeText($translator->trans('community.comments.post')) ?></button>
        </form>
    <?php elseif (!$view->boolean('authenticated') && $view->string('comment_policy') !== 'DISABLED') : ?>
        <p><a href="/login"><?= $escape->escapeText($translator->trans('community.comments.sign_in')) ?></a></p>
    <?php else : ?>
        <p><?= $escape->escapeText($translator->trans('community.comments.disabled')) ?></p>
    <?php endif; ?>
</section>
