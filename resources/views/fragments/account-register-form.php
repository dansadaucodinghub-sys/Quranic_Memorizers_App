<?php

declare(strict_types=1);

$errors = $view->array('errors');
?>
<section class="identity-form-region" data-qmdb-fragment-root data-qmdb-form-region>
    <?= $renderer->render('components.form-error-summary', new \Qmdb\Shared\Presentation\View\ViewData([
        'errors' => $errors,
        'global_error' => $view->string('global_error'),
    ]), $translator)->trustedHtml() ?>
    <form method="post" action="/register" data-qmdb-progressive-form novalidate>
        <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
        <input type="hidden" name="registration_submission_id" value="<?= $escape->escapeAttribute($view->string('submission_id')) ?>" data-qmdb-idempotency-key>
        <div class="form-field">
            <label for="email"><?= $escape->escapeText($translator->trans('form.email')) ?> <span aria-hidden="true">*</span></label>
            <input id="email" name="email" type="email" required autocomplete="email" maxlength="320" value="<?= $escape->escapeAttribute($view->string('email')) ?>"<?= isset($errors['email']) ? ' aria-invalid="true" aria-describedby="email-error"' : '' ?>>
            <?= $renderer->render('components.form-field-error', new \Qmdb\Shared\Presentation\View\ViewData([
                'field' => 'email',
                'message' => is_string($errors['email'] ?? null) ? $errors['email'] : '',
            ]), $translator)->trustedHtml() ?>
        </div>
        <div class="form-field">
            <label for="password"><?= $escape->escapeText($translator->trans('form.password')) ?> <span aria-hidden="true">*</span></label>
            <input id="password" name="password" type="password" required autocomplete="new-password" maxlength="1024" aria-describedby="password-requirements<?= isset($errors['password']) ? ' password-error' : '' ?>"<?= isset($errors['password']) ? ' aria-invalid="true"' : '' ?>>
            <?= $renderer->render('components.form-field-error', new \Qmdb\Shared\Presentation\View\ViewData([
                'field' => 'password',
                'message' => is_string($errors['password'] ?? null) ? $errors['password'] : '',
            ]), $translator)->trustedHtml() ?>
        </div>
        <div class="form-field">
            <label for="password_confirmation"><?= $escape->escapeText($translator->trans('form.password_confirmation')) ?> <span aria-hidden="true">*</span></label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" maxlength="1024">
        </div>
        <?= $renderer->render('components.password-requirements', new \Qmdb\Shared\Presentation\View\ViewData(), $translator)->trustedHtml() ?>
        <button class="button primary" type="submit"><?= $escape->escapeText($translator->trans('registration.submit')) ?></button>
    </form>
</section>
