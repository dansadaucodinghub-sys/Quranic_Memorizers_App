<?php

declare(strict_types=1);
?>
<section class="identity-form-region" data-qmdb-fragment-root data-qmdb-form-region>
    <?= $renderer->render('components.form-error-summary', new \Qmdb\Shared\Presentation\View\ViewData([
        'errors' => $view->array('errors'),
        'global_error' => $view->string('global_error'),
    ]), $translator)->trustedHtml() ?>
    <?php if ($view->boolean('link_valid')): ?>
        <form method="post" action="/verify-email/<?= $escape->escapeAttribute($view->string('challenge_id')) ?>" data-qmdb-progressive-form novalidate>
            <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
            <input type="hidden" name="token" value="<?= $escape->escapeAttribute($view->string('token')) ?>">
            <button class="button primary" type="submit"><?= $escape->escapeText($translator->trans('verification.confirm.submit')) ?></button>
        </form>
    <?php else: ?>
        <p role="alert"><?= $escape->escapeText($translator->trans('form.error.verification')) ?></p>
        <p><a href="/verify-email/resend"><?= $escape->escapeText($translator->trans('verification.resend.link')) ?></a></p>
    <?php endif; ?>
</section>
