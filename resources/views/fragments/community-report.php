<?php

declare(strict_types=1);

$clipId = $view->string('clip_id');
$error = $view->string('error');
?>
<section data-qmdb-fragment-root data-qmdb-form-region aria-label="<?= $escape->escapeAttribute($translator->trans('community.report.title')) ?>">
    <?php if ($view->boolean('submitted')) : ?>
        <p role="status" tabindex="-1" data-qmdb-completion-heading><?= $escape->escapeText($translator->trans('community.report.submitted')) ?></p>
    <?php else : ?>
        <p><?= $escape->escapeText($translator->trans('community.report.private_notice')) ?></p>
        <?php if ($error !== '') : ?>
            <div role="alert" class="error-summary" tabindex="-1" data-qmdb-error-summary>
                <p><?= $escape->escapeText($translator->trans($error)) ?></p>
            </div>
        <?php endif; ?>
        <?php if ($clipId !== '' && $error !== 'community.report.unavailable') : ?>
            <form method="post" data-qmdb-progressive-form action="/community/reports" data-qmdb-async-form>
                <input type="hidden" name="clip_id" value="<?= $escape->escapeAttribute($clipId) ?>">
                <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
                <input type="hidden" name="submission_id" data-qmdb-idempotency-key value="<?= $escape->escapeAttribute($view->string('submission_id')) ?>">
                <p><label for="community-report-reason"><?= $escape->escapeText($translator->trans('community.report.reason')) ?></label>
                    <select id="community-report-reason" name="reason_code" required>
                        <?php foreach (['CHILD_SAFETY', 'HARASSMENT', 'HATE', 'PRIVACY', 'RIGHTS', 'MISLEADING_REFERENCE', 'OTHER'] as $reason) : ?>
                            <option value="<?= $escape->escapeAttribute($reason) ?>"><?= $escape->escapeText($translator->trans('community.report.reason.' . strtolower($reason))) ?></option>
                        <?php endforeach; ?>
                    </select></p>
                <p><label for="community-report-statement"><?= $escape->escapeText($translator->trans('community.report.statement')) ?></label>
                    <textarea id="community-report-statement" name="statement" rows="5" maxlength="2000" required></textarea></p>
                <p><button type="submit"><?= $escape->escapeText($translator->trans('community.report.submit')) ?></button></p>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</section>
