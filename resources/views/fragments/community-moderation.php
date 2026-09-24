<?php

declare(strict_types=1);

$queue = $view->list('queue');
$caseId = $view->string('case_id');
$reports = $view->list('reports');
$heldComments = $view->list('held_comments');
?>
<section data-qmdb-fragment-root aria-label="<?= $escape->escapeAttribute($translator->trans('community.moderation.title')) ?>">
    <p><?= $escape->escapeText($translator->trans('community.moderation.private_notice')) ?></p>
    <?php if ($view->boolean('saved')) : ?>
        <p role="status"><?= $escape->escapeText($translator->trans('community.moderation.saved')) ?></p>
    <?php endif; ?>
    <?php if ($view->string('error') !== '') : ?>
        <p role="alert"><?= $escape->escapeText($translator->trans($view->string('error'))) ?></p>
    <?php endif; ?>
    <?php if ($queue === []) : ?>
        <p><?= $escape->escapeText($translator->trans('community.moderation.empty')) ?></p>
    <?php else : ?>
        <ol class="community-creator-list">
            <?php foreach ($queue as $item) : ?>
                <li><article>
                    <h2><bdi><?= $escape->escapeText($item['public_id']) ?></bdi></h2>
                    <p><?= $escape->escapeText($translator->trans('community.moderation.priority')) ?>: <?= $escape->escapeText($item['priority']) ?></p>
                    <p><?= $escape->escapeText($translator->trans('community.moderation.status')) ?>: <?= $escape->escapeText($item['status']) ?></p>
                    <p><?= $escape->escapeText($translator->trans('community.moderation.reports')) ?>: <?= $escape->escapeText((string) $item['report_count']) ?></p>
                    <p><?= $escape->escapeText($translator->trans('community.moderation.clip_status')) ?>: <?= $escape->escapeText($item['clip_status']) ?></p>
                    <p><a href="/workspace/media/<?= $escape->escapeAttribute($item['asset_id']) ?>/content"><?= $escape->escapeText($translator->trans('community.moderation.media')) ?></a></p>
                    <p><a href="/workspace/community/moderation/<?= $escape->escapeAttribute($item['public_id']) ?>"><?= $escape->escapeText($translator->trans('community.moderation.view_reports')) ?></a></p>
                    <?php if ($caseId === $item['public_id']) : ?>
                        <section aria-label="<?= $escape->escapeAttribute($translator->trans('community.moderation.report_details')) ?>">
                            <h3><?= $escape->escapeText($translator->trans('community.moderation.report_details')) ?></h3>
                            <ol>
                                <?php foreach ($reports as $report) : ?>
                                    <li><p><?= $escape->escapeText($translator->trans('community.moderation.reason')) ?>: <?= $escape->escapeText($report['reason']) ?></p>
                                        <p><?= $escape->escapeText($report['statement']) ?></p></li>
                                <?php endforeach; ?>
                            </ol>
                        </section>
                    <?php endif; ?>
                    <?php $operation = in_array($item['status'], ['SUBMITTED', 'TRIAGED'], true)
                        ? 'assign' : ($item['assigned_to_me'] && in_array($item['status'], ['ASSIGNED', 'ACTIONED'], true)
                            ? 'start' : ($item['assigned_to_me'] && $item['status'] === 'UNDER_REVIEW' ? 'decide' : '')); ?>
                    <?php if ($operation !== '') : ?>
                        <form method="post" data-qmdb-progressive-form action="/workspace/community/moderation/<?= $escape->escapeAttribute($item['public_id']) ?>/<?= $escape->escapeAttribute($operation) ?>">
                            <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
                            <input type="hidden" name="submission_id" data-qmdb-idempotency-key value="<?= $escape->escapeAttribute($item['submission_id']) ?>">
                            <input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute((string) $item['version']) ?>">
                            <?php if ($operation === 'decide') : ?>
                                <p><label for="moderation-action-<?= $escape->escapeAttribute($item['public_id']) ?>"><?= $escape->escapeText($translator->trans('community.moderation.action')) ?></label>
                                    <select id="moderation-action-<?= $escape->escapeAttribute($item['public_id']) ?>" name="action">
                                        <?php foreach (['NO_ACTION', 'HIDE', 'REMOVE', 'RESTORE', 'ESCALATE'] as $action) : ?>
                                            <option value="<?= $escape->escapeAttribute($action) ?>"><?= $escape->escapeText($translator->trans('community.moderation.action.' . strtolower($action))) ?></option>
                                        <?php endforeach; ?>
                                    </select></p>
                                <p><label for="moderation-reason-<?= $escape->escapeAttribute($item['public_id']) ?>"><?= $escape->escapeText($translator->trans('community.moderation.reason')) ?></label>
                                    <select id="moderation-reason-<?= $escape->escapeAttribute($item['public_id']) ?>" name="reason_code">
                                        <?php foreach (['CHILD_SAFETY', 'HARASSMENT', 'HATE', 'PRIVACY', 'RIGHTS', 'MISLEADING_REFERENCE', 'OTHER'] as $reason) : ?>
                                            <option value="<?= $escape->escapeAttribute($reason) ?>"><?= $escape->escapeText($translator->trans('community.report.reason.' . strtolower($reason))) ?></option>
                                        <?php endforeach; ?>
                                    </select></p>
                            <?php endif; ?>
                            <button type="submit"><?= $escape->escapeText($translator->trans('community.moderation.' . $operation)) ?></button>
                        </form>
                    <?php endif; ?>
                </article></li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <section aria-labelledby="held-comments-heading">
        <h2 id="held-comments-heading"><?= $escape->escapeText($translator->trans('community.moderation.held_comments')) ?></h2>
        <?php if ($heldComments === []) : ?>
            <p><?= $escape->escapeText($translator->trans('community.moderation.held_comments_empty')) ?></p>
        <?php else : ?>
            <ol class="community-creator-list">
                <?php foreach ($heldComments as $comment) : ?>
                    <li><article>
                        <h3><bdi><?= $escape->escapeText($comment['public_id']) ?></bdi></h3>
                        <p><?= $escape->escapeText($comment['body']) ?></p>
                        <form method="post" data-qmdb-progressive-form action="/workspace/community/moderation/comments/<?= $escape->escapeAttribute($comment['public_id']) ?>/decide">
                            <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
                            <input type="hidden" name="submission_id" data-qmdb-idempotency-key value="<?= $escape->escapeAttribute($comment['submission_id']) ?>">
                            <input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute((string) $comment['version']) ?>">
                            <p><label for="comment-action-<?= $escape->escapeAttribute($comment['public_id']) ?>"><?= $escape->escapeText($translator->trans('community.moderation.comment_action')) ?></label>
                                <select id="comment-action-<?= $escape->escapeAttribute($comment['public_id']) ?>" name="action">
                                    <option value="APPROVE"><?= $escape->escapeText($translator->trans('community.moderation.comment_approve')) ?></option>
                                    <option value="REMOVE"><?= $escape->escapeText($translator->trans('community.moderation.comment_remove')) ?></option>
                                </select></p>
                            <button type="submit"><?= $escape->escapeText($translator->trans('community.moderation.decide')) ?></button>
                        </form>
                    </article></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</section>
