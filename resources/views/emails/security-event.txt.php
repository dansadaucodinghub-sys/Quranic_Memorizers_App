<?php

declare(strict_types=1);
?>
<?= $translator->trans($view->string('heading_key')) . "\n\n" ?>
<?= $translator->trans($view->string('body_key'), ['time' => $view->string('occurred_at')]) . "\n" ?>
<?= $translator->trans('email.security_event.unauthorized') . "\n\n" ?>
<?= $view->string('login_url') . "\n" ?>
