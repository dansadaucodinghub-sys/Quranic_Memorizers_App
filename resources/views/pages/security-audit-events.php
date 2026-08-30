<?php declare(strict_types=1); ?>
<article class="shell identity-page"><p class="eyebrow"><?= $escape->escapeText($translator->trans('security_audit.eyebrow')) ?></p><h1><?= $escape->escapeText($translator->trans('security_audit.events_heading')) ?></h1><?= $renderer->render('fragments.security-audit-events', $view, $translator)->trustedHtml() ?></article>
