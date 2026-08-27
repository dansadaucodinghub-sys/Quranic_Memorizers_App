<?php

declare(strict_types=1);
?>
<?= $translator->trans('email.password_reset_completed.heading') . "\n\n" ?>
<?= $translator->trans('email.password_reset_completed.body', ['time' => $view->string('occurred_at')]) . "\n" ?>
<?= $translator->trans('email.password_reset_completed.sessions') . "\n" ?>
<?= $translator->trans('email.password_reset_completed.unauthorized') . "\n\n" ?>
<?= $view->string('login_url') . "\n" ?>
