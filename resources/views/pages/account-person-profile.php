<?php

declare(strict_types=1);

?>
<article class="shell identity-page" data-qmdb-private-person-profile>
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('person_profile.eyebrow')) ?></p>
    <h1><?= $escape->escapeText($translator->trans('person_profile.heading')) ?></h1>
    <p class="lead"><?= $escape->escapeText($translator->trans('person_profile.intro')) ?></p>
    <?= $renderer->render('fragments.account-person-profile', $view, $translator)->trustedHtml() ?>
</article>
