<?php

declare(strict_types=1);

$errors = $view->array('errors');
?>
<section class="identity-form-region" data-qmdb-fragment-root data-qmdb-form-region>
    <?= $renderer->render('components.form-error-summary', new \Qmdb\Shared\Presentation\View\ViewData([
        'errors' => $errors,
        'global_error' => $view->string('global_error'),
    ]), $translator)->trustedHtml() ?>
    <form method="post" action="/forgot-password" data-qmdb-progressive-form novalidate>
        <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
        <input type="hidden" name="recovery_request_submission_id" value="<?= $escape->escapeAttribute($view->string('submission_id')) ?>" data-qmdb-idempotency-key>
        <div class="form-field">
            <label for="recovery-email"><?= $escape->escapeText($translator->trans('form.email')) ?> <span aria-hidden="true">*</span></label>
            <input id="recovery-email" name="email" type="email" required autocomplete="email" maxlength="320" value="<?= $escape->escapeAttribute($view->string('email')) ?>"<?= isset($errors['email']) ? ' aria-invalid="true" aria-describedby="recovery-email-error"' : '' ?>>
            <?= $renderer->render('components.form-field-error', new \Qmdb\Shared\Presentation\View\ViewData([
                'field' => 'recovery-email',
                'message' => is_string($errors['email'] ?? null) ? $errors['email'] : '',
            ]), $translator)->trustedHtml() ?>
        </div>
        <button class="button primary" type="submit"><?= $escape->escapeText($translator->trans('password_recovery.submit')) ?></button>
    </form>
</section>
