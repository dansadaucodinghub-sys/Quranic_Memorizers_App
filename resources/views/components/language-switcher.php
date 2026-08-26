<?php

declare(strict_types=1);

use Qmdb\Shared\Presentation\Html\SafeUrl;

$path = SafeUrl::applicationRelative($view->string('current_path'));
$current = $translator->locale()->value();
?>
<nav class="language-switcher" aria-label="<?= $escape->escapeAttribute($translator->trans('a11y.language_selector')) ?>">
    <a href="<?= $escape->escapeAttribute($path->withQuery(['lang' => 'en'])->value()) ?>" hreflang="en" lang="en"<?= $current === 'en' ? ' aria-current="page"' : '' ?>><?= $escape->escapeText($translator->trans('language.english')) ?></a>
    <a href="<?= $escape->escapeAttribute($path->withQuery(['lang' => 'ar'])->value()) ?>" hreflang="ar" lang="ar" dir="rtl"<?= $current === 'ar' ? ' aria-current="page"' : '' ?>><?= $escape->escapeText($translator->trans('language.arabic')) ?></a>
</nav>
