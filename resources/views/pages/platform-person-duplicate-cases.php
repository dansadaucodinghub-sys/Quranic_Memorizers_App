<?php declare(strict_types=1); ?>
<article class="shell identity-page"><p class="eyebrow"><?= $escape->escapeText($translator->trans('identity_resolution.platform_eyebrow')) ?></p><h1><?= $escape->escapeText($translator->trans('identity_resolution.platform_duplicate_heading')) ?></h1><?= $renderer->render('fragments.person-identity-resolution', $view, $translator)->trustedHtml() ?></article>
