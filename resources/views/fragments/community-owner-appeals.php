<?php

declare(strict_types=1);

$items = $view->list('items');
?>
<section data-qmdb-fragment-root aria-label="<?= $escape->escapeAttribute($translator->trans('community.appeal.owner_title')) ?>">
    <p><?= $escape->escapeText($translator->trans('community.appeal.owner_notice')) ?></p>
    <?php if ($view->boolean('saved')) : ?>
        <p role="status"><?= $escape->escapeText($translator->trans('community.appeal.submitted')) ?></p>
    <?php endif; ?>
    <?php if ($view->string('error') !== '') : ?>
        <p role="alert"><?= $escape->escapeText($translator->trans($view->string('error'))) ?></p>
    <?php endif; ?>
    <?php if ($items === []) : ?>
        <p><?= $escape->escapeText($translator->trans('community.appeal.owner_empty')) ?></p>
    <?php else : ?>
        <ol class="community-creator-list">
            <?php foreach ($items as $item) : ?>
                <li><article>
                    <h2><?= $escape->escapeText($translator->trans('community.appeal.clip')) ?> <bdi><?= $escape->escapeText($item['clip_id']) ?></bdi></h2>
                    <p><?= $escape->escapeText($translator->trans('community.appeal.action')) ?>: <?= $escape->escapeText($item['action']) ?></p>
                    <p><?= $escape->escapeText($translator->trans('community.appeal.decided_at')) ?>: <bdi><?= $escape->escapeText($item['decided_at']) ?></bdi> UTC</p>
                    <form method="post" data-qmdb-progressive-form action="/account/community/appeals/<?= $escape->escapeAttribute($item['case_id']) ?>">
                        <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
                        <input type="hidden" name="submission_id" data-qmdb-idempotency-key value="<?= $escape->escapeAttribute($item['submission_id']) ?>">
                        <p><label for="appeal-statement-<?= $escape->escapeAttribute($item['case_id']) ?>"><?= $escape->escapeText($translator->trans('community.appeal.statement')) ?></label>
                            <textarea id="appeal-statement-<?= $escape->escapeAttribute($item['case_id']) ?>" name="statement" maxlength="2000" required></textarea></p>
                        <button type="submit"><?= $escape->escapeText($translator->trans('community.appeal.submit')) ?></button>
                    </form>
                </article></li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
