<?php

declare(strict_types=1);
?>
<div class="theme-control">
    <label for="qmdb-theme"><?= $escape->escapeText($translator->trans('a11y.theme_selector')) ?></label>
    <select id="qmdb-theme" data-qmdb-theme>
        <option value="system"><?= $escape->escapeText($translator->trans('theme.system')) ?></option>
        <option value="light"><?= $escape->escapeText($translator->trans('theme.light')) ?></option>
        <option value="dark"><?= $escape->escapeText($translator->trans('theme.dark')) ?></option>
        <option value="high-contrast"><?= $escape->escapeText($translator->trans('theme.high_contrast')) ?></option>
        <option value="emerald-gold"><?= $escape->escapeText($translator->trans('theme.emerald_gold')) ?></option>
    </select>
</div>
