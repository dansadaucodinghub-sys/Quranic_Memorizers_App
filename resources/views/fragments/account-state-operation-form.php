<?php

declare(strict_types=1);

$account = $view->array('account');
$operation = $view->string('operation');
$action = '/platform/security/accounts/' . rawurlencode((string)$account['public_id']) . '/' . strtolower($operation === 'SUSPEND' ? 'suspend' : 'reactivate');
?>
<section data-qmdb-fragment-root data-qmdb-modal-content data-qmdb-dialog-title="<?= $escape->escapeAttribute($operation === 'SUSPEND' ? $translator->trans('account_state.suspend') : $translator->trans('account_state.reactivate')) ?>" data-qmdb-form-region aria-labelledby="account-state-operation-heading">
    <h2 id="account-state-operation-heading" tabindex="-1"><?= $escape->escapeText($operation === 'SUSPEND' ? $translator->trans('account_state.suspend') : $translator->trans('account_state.reactivate')) ?></h2>
    <p><?= $escape->escapeText($operation === 'SUSPEND' ? $translator->trans('account_state.suspend_consequence') : $translator->trans('account_state.reactivate_consequence')) ?></p>
    <form method="post" action="<?= $escape->escapeAttribute($action) ?>" data-qmdb-progressive-form>
        <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
        <input type="hidden" name="expected_account_version" value="<?= $escape->escapeAttribute((string)$account['version']) ?>">
        <input type="hidden" name="submission_id" value="<?= $escape->escapeAttribute($view->string('submission_id')) ?>">
        <label><?= $escape->escapeText($translator->trans('account_state.reason')) ?><select name="reason_code" required><?php foreach ($view->list('reason_codes') as $reason): ?><option value="<?= $escape->escapeAttribute(is_string($reason) ? $reason : '') ?>"><?= $escape->escapeText(is_string($reason) ? $reason : '') ?></option><?php endforeach; ?></select></label>
        <label><?= $escape->escapeText($translator->trans('account_state.justification')) ?><textarea name="justification" required maxlength="2000"></textarea></label>
        <label><?= $escape->escapeText($translator->trans('account_state.reference')) ?><input name="reference_code" maxlength="128"></label>
        <a class="button" href="/platform/security/accounts/<?= $escape->escapeAttribute($account['public_id']) ?>" data-qmdb-modal-close><?= $escape->escapeText($translator->trans('action.cancel')) ?></a>
        <button class="button <?= $operation === 'SUSPEND' ? 'danger' : '' ?>" type="submit"><?= $escape->escapeText($operation === 'SUSPEND' ? $translator->trans('account_state.suspend') : $translator->trans('account_state.reactivate')) ?></button>
    </form>
</section>
