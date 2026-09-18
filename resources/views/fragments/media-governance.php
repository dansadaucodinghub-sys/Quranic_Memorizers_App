<?php

declare(strict_types=1);

$action = $view->string('action');
$error = $view->string('error');
?>
<section data-qmdb-fragment-root data-qmdb-form-region aria-label="<?= $escape->escapeAttribute($translator->trans('media.governance.title')) ?>">
    <p><?= $escape->escapeText($translator->trans('media.governance.notice')) ?></p>
    <?php if ($error !== '') : ?>
        <div data-qmdb-error-summary role="alert" class="error-summary" tabindex="-1"><h2><?= $escape->escapeText($translator->trans('media.governance.error_title')) ?></h2><p><?= $escape->escapeText($translator->trans($error)) ?></p><a href="#reason-code"><?= $escape->escapeText($translator->trans('media.governance.review')) ?></a></div>
    <?php else : ?>
        <div data-qmdb-error-summary hidden tabindex="-1"></div>
    <?php endif; ?>
    <?php if ($view->boolean('saved')) :
        ?><p role="status" data-qmdb-completion-heading tabindex="-1"><?= $escape->escapeText($translator->trans('media.governance.saved')) ?></p><?php
    endif; ?>
    <?php foreach ($view->list('records') as $record) : ?>
        <section>
            <h2><bdi><?= $escape->escapeText($record['public_id']) ?></bdi></h2>
            <p><?= $escape->escapeText($translator->trans('media.governance.status')) ?>: <bdi><?= $escape->escapeText($record['status']) ?></bdi></p>
            <?php if ($record['held']) :
                ?><p><?= $escape->escapeText($translator->trans('media.governance.held')) ?></p><?php
            endif; ?>
            <?php if ($action === '') : ?>
                <nav aria-label="<?= $escape->escapeAttribute($translator->trans('media.governance.actions')) ?>"><ul>
                    <?php foreach (['approve','reject','hold','release-hold','remove','archive','withdraw-consent','consent-review'] as $operation) : ?>
                        <li><a href="/workspace/media/<?= $escape->escapeAttribute($record['public_id']) ?>/<?= $escape->escapeAttribute($operation) ?>"><?= $escape->escapeText($translator->trans('media.governance.' . str_replace('-', '_', $operation))) ?></a></li>
                    <?php endforeach; ?>
                </ul></nav>
            <?php else : ?>
                <h3><?= $escape->escapeText($translator->trans('media.governance.' . str_replace('-', '_', $action))) ?></h3>
                <?php if ($view->string('step_up') !== '') :
                    ?><p><a href="/account/step-up/<?= $escape->escapeAttribute($view->string('step_up')) ?>"><?= $escape->escapeText($translator->trans('media.governance.step_up')) ?></a></p><?php
                endif; ?>
                <form method="post" action="/workspace/media/<?= $escape->escapeAttribute($record['public_id']) ?>/<?= $escape->escapeAttribute($action) ?>" data-qmdb-progressive-form>
                    <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
                    <input type="hidden" name="submission_id" data-qmdb-idempotency-key value="<?= $escape->escapeAttribute($view->string('submission_id')) ?>">
                    <input type="hidden" name="version" value="<?= $escape->escapeAttribute((string) $record['version']) ?>">
                    <input type="hidden" name="tenant_context_version" value="<?= $escape->escapeAttribute($view->string('tenant_context_version')) ?>">
                    <input type="hidden" name="workspace_id" value="<?= $escape->escapeAttribute($view->string('workspace_id')) ?>">
                    <label for="reason-code"><?= $escape->escapeText($translator->trans('media.governance.reason')) ?></label>
                    <input id="reason-code" name="reason_code" pattern="[A-Z][A-Z0-9_]{1,47}" maxlength="48" required aria-describedby="reason-help" dir="ltr">
                    <p id="reason-help"><?= $escape->escapeText($translator->trans('media.governance.reason_help')) ?></p>
                    <?php if ($action === 'consent-review') : ?>
                        <p id="consent-evidence-help"><?= $escape->escapeText($translator->trans('media.governance.evidence_notice')) ?></p>
                        <?php foreach (['evidence_reference', 'evidence_sha256', 'participants', 'participant_consents', 'minors', 'guardian_consents'] as $field) : ?>
                            <label for="<?= $escape->escapeAttribute($field) ?>"><?= $escape->escapeText($translator->trans('media.governance.' . $field)) ?></label>
                            <input id="<?= $escape->escapeAttribute($field) ?>" name="<?= $escape->escapeAttribute($field) ?>" required maxlength="64" aria-describedby="consent-evidence-help" dir="ltr">
                        <?php endforeach; ?>
                        <?php foreach (['rights_verified', 'organization_authority_verified'] as $field) : ?>
                            <label><input type="checkbox" name="<?= $escape->escapeAttribute($field) ?>" value="1" required> <?= $escape->escapeText($translator->trans('media.governance.' . $field)) ?></label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (in_array($action, ['hold','release-hold'], true)) : ?>
                        <label for="hold-code"><?= $escape->escapeText($translator->trans('media.governance.hold_code')) ?></label>
                        <select id="hold-code" name="hold_code"><?php foreach (['TECHNICAL','SECURITY','CONSENT','RIGHTS','GOVERNANCE'] as $hold) :
                            ?><option value="<?= $escape->escapeAttribute($hold) ?>"><?= $escape->escapeText($hold) ?></option><?php
                                                                endforeach; ?></select>
                    <?php else :
                        ?><input type="hidden" name="hold_code" value="GOVERNANCE"><?php
                    endif; ?>
                    <button class="button" type="submit"><?= $escape->escapeText($translator->trans('media.governance.confirm')) ?></button>
                </form>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</section>
