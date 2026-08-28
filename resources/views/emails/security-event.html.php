<?php

declare(strict_types=1);

$locale = $view->string('locale');
?>
<!doctype html>
<html lang="<?= $escape->escapeAttribute($locale) ?>" dir="<?= $locale === 'ar' ? 'rtl' : 'ltr' ?>">
<body>
    <h1><?= $escape->escapeText($translator->trans($view->string('heading_key'))) ?></h1>
    <p><?= $escape->escapeText($translator->trans(
        $view->string('body_key'),
        ['time' => $view->string('occurred_at')],
    )) ?></p>
    <p><?= $escape->escapeText($translator->trans('email.security_event.unauthorized')) ?></p>
    <p><a href="<?= $escape->escapeAttribute($view->string('login_url')) ?>"><?= $escape->escapeText($translator->trans('password_recovery.login')) ?></a></p>
</body>
</html>
