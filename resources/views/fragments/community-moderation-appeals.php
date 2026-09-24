<?php

declare(strict_types=1);

$items = $view->list('items');
$selected = $view->string('selected');
?>
<section data-qmdb-fragment-root aria-label="<?= $escape->escapeAttribute($translator->trans('community.appeal.review_title')) ?>">
    <p><?= $escape->escapeText($translator->trans('community.appeal.review_notice')) ?></p>
    <?php if ($view->boolean('saved')) : ?>
        <p role="status"><?= $escape->escapeText($translator->trans('community.appeal.review_saved')) ?></p>
    <?php endif; ?>
    <?php if ($view->string('error') !== '') : ?>
        <p role="alert"><?= $escape->escapeText($translator->trans($view->string('error'))) ?></p>
    <?php endif; ?>
    <?php if ($items === []) : ?>
        <p><?= $escape->escapeText($translator->trans('community.appeal.review_empty')) ?></p>
    <?php else : ?>
        <ol class="community-creator-list">
            <?php foreach ($items as $item) : ?>
                <li><article>
                    <h2><bdi><?= $escape->escapeText($item['public_id']) ?></bdi></h2>
                    <p><?= $escape->escapeText($translator->trans('community.appeal.clip')) ?>: <bdi><?= $escape->escapeText($item['clip_id']) ?></bdi></p>
                    <p><a href="/workspace/community/appeals/<?= $escape->escapeAttribute($item['public_id']) ?>"><?= $escape->escapeText($translator->trans('community.appeal.view')) ?></a></p>
                    <?php if ($selected === $item['public_id']) : ?>
                        <section aria-label="<?= $escape->escapeAttribute($translator->trans('community.appeal.statement')) ?>">
                            <h3><?= $escape->escapeText($translator->trans('community.appeal.statement')) ?></h3>
                            <p><?= $escape->escapeText($view->string('statement')) ?></p>
                        </section>
                        <form method="post" data-qmdb-progressive-form action="/workspace/community/appeals/<?= $escape->escapeAttribute($item['public_id']) ?>/decide">
                            <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
                            <input type="hidden" name="submission_id" data-qmdb-idempotency-key value="<?= $escape->escapeAttribute($item['submission_id']) ?>">
                            <input type="hidden" name="expected_version" value="1">
                            <p><label for="appeal-outcome-<?= $escape->escapeAttribute($item['public_id']) ?>"><?= $escape->escapeText($translator->trans('community.appeal.outcome')) ?></label>
                                <select id="appeal-outcome-<?= $escape->escapeAttribute($item['public_id']) ?>" name="outcome">
                                    <option value="UPHELD"><?= $escape->escapeText($translator->trans('community.appeal.upheld')) ?></option>
                                    <option value="RESTORED"><?= $escape->escapeText($translator->trans('community.appeal.restored')) ?></option>
                                </select></p>
                            <p><label for="appeal-reason-<?= $escape->escapeAttribute($item['public_id']) ?>"><?= $escape->escapeText($translator->trans('community.appeal.reason')) ?></label>
                                <select id="appeal-reason-<?= $escape->escapeAttribute($item['public_id']) ?>" name="reason_code">
                                    <?php foreach (['EVIDENCE_CONFIRMED', 'EVIDENCE_CHANGED', 'POLICY_CORRECTION', 'OTHER'] as $reason) : ?>
                                        <option value="<?= $escape->escapeAttribute($reason) ?>"><?= $escape->escapeText($translator->trans('community.appeal.reason.' . strtolower($reason))) ?></option>
                                    <?php endforeach; ?>
                                </select></p>
                            <button type="submit"><?= $escape->escapeText($translator->trans('community.appeal.decide')) ?></button>
                        </form>
                    <?php endif; ?>
                </article></li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
