<?php

declare(strict_types=1);

$locale = $view->string('locale');
?>
<!doctype html>
<html lang="<?= $escape->escapeAttribute($locale) ?>" dir="<?= $locale === 'ar' ? 'rtl' : 'ltr' ?>">
<body>
    <h1><?= $escape->escapeText($translator->trans('email.recovery.heading')) ?></h1>
    <p><?= $escape->escapeText($translator->trans('email.recovery.instructions')) ?></p>
    <p><a href="<?= $escape->escapeAttribute($view->string('url')) ?>"><?= $escape->escapeText($translator->trans('email.recovery.action')) ?></a></p>
    <p><?= $escape->escapeText($translator->trans('email.recovery.expires', ['expiry' => $view->string('expiry')])) ?></p>
    <p><?= $escape->escapeText($translator->trans('email.recovery.ignore')) ?></p>
</body>
</html>
