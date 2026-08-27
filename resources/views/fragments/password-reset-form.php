<?php

declare(strict_types=1);

$errors = $view->array('errors');
?>
<section class="identity-form-region" data-qmdb-fragment-root data-qmdb-form-region>
    <?= $renderer->render('components.form-error-summary', new \Qmdb\Shared\Presentation\View\ViewData([
        'errors' => $errors,
        'global_error' => $view->string('global_error'),
    ]), $translator)->trustedHtml() ?>
    <?php if ($view->boolean('link_valid')): ?>
        <form method="post" action="/reset-password/<?= $escape->escapeAttribute($view->string('challenge_id')) ?>" data-qmdb-progressive-form novalidate>
            <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
            <input type="hidden" name="token" value="<?= $escape->escapeAttribute($view->string('token')) ?>">
            <input type="hidden" name="password_reset_submission_id" value="<?= $escape->escapeAttribute($view->string('submission_id')) ?>" data-qmdb-idempotency-key>
            <div class="form-field">
                <label for="new-password"><?= $escape->escapeText($translator->trans('password_reset.new_password')) ?> <span aria-hidden="true">*</span></label>
                <input id="new-password" name="new_password" type="password" required autocomplete="new-password" maxlength="1024" aria-describedby="password-requirements<?= isset($errors['new_password']) ? ' new-password-error' : '' ?>"<?= isset($errors['new_password']) ? ' aria-invalid="true"' : '' ?>>
                <?= $renderer->render('components.form-field-error', new \Qmdb\Shared\Presentation\View\ViewData([
                    'field' => 'new-password',
                    'message' => is_string($errors['new_password'] ?? null) ? $errors['new_password'] : '',
                ]), $translator)->trustedHtml() ?>
            </div>
            <div class="form-field">
                <label for="new-password-confirmation"><?= $escape->escapeText($translator->trans('password_reset.confirmation')) ?> <span aria-hidden="true">*</span></label>
                <input id="new-password-confirmation" name="new_password_confirmation" type="password" required autocomplete="new-password" maxlength="1024">
            </div>
            <?= $renderer->render('components.password-requirements', new \Qmdb\Shared\Presentation\View\ViewData(), $translator)->trustedHtml() ?>
            <button class="button primary" type="submit"><?= $escape->escapeText($translator->trans('password_reset.submit')) ?></button>
        </form>
    <?php else: ?>
        <p role="alert"><?= $escape->escapeText($translator->trans('form.error.recovery')) ?></p>
        <p><a href="/forgot-password"><?= $escape->escapeText($translator->trans('password_recovery.again')) ?></a></p>
    <?php endif; ?>
</section>
