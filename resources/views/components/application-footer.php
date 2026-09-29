<?php

declare(strict_types=1);
?>
<footer class="site-footer">
    <div class="shell footer-grid">
        <div class="footer-brand">
            <img src="/assets/brand/musabaqahub-app-icon.png" width="52" height="52" alt="">
            <span><strong><?= $escape->escapeText($translator->trans('app.name')) ?></strong><small><?= $escape->escapeText($translator->trans('app.tagline')) ?></small></span>
        </div>
        <p><?= $escape->escapeText($translator->trans('footer.scope')) ?></p>
        <p class="footer-promise"><?= $escape->escapeText($translator->trans('brand.promise')) ?></p>
    </div>
</footer>
