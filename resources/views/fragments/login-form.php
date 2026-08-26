<?php

declare(strict_types=1);
?>
<section class="identity-form-region" data-qmdb-fragment-root data-qmdb-form-region>
    <?= $renderer->render('components.form-error-summary', new \Qmdb\Shared\Presentation\View\ViewData([
        'errors' => $view->array('errors'),
        'global_error' => $view->string('global_error'),
    ]), $translator)->trustedHtml() ?>
    <form method="post" action="/login" data-qmdb-progressive-form novalidate>
        <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
        <input type="hidden" name="login_submission_id" value="<?= $escape->escapeAttribute($view->string('submission_id')) ?>" data-qmdb-idempotency-key>
        <div class="form-field">
            <label for="login-email"><?= $escape->escapeText($translator->trans('form.email')) ?></label>
            <input id="login-email" name="email" type="email" required autocomplete="email" maxlength="320" value="<?= $escape->escapeAttribute($view->string('email')) ?>">
        </div>
        <div class="form-field">
            <label for="login-password"><?= $escape->escapeText($translator->trans('form.password')) ?></label>
            <input id="login-password" name="password" type="password" required autocomplete="current-password" maxlength="1024">
        </div>
        <button class="button primary" type="submit"><?= $escape->escapeText($translator->trans('login.submit')) ?></button>
    </form>
    <p><a href="/register"><?= $escape->escapeText($translator->trans('login.register')) ?></a></p>
    <p><a href="/verify-email/resend"><?= $escape->escapeText($translator->trans('login.resend')) ?></a></p>
</section>
