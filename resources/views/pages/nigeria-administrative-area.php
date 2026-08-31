<?php

declare(strict_types=1);

$area = $view->array('area');
$children = $view->list('children');
?>
<article class="shell prose-page geography-area">
    <p><a href="/locations/nigeria?lang=<?= $escape->escapeAttribute($translator->locale()->value()) ?>"><?= $escape->escapeText($translator->trans('geography.back_to_nigeria')) ?></a></p>
    <p class="eyebrow"><?= $escape->escapeText($translator->trans('geography.administrative_area')) ?></p>
    <h1><?= $escape->escapeText((string) ($area['official_name'] ?? '')) ?></h1>
    <dl class="facts"><div><dt><?= $escape->escapeText($translator->trans('geography.area_type')) ?></dt><dd><?= $escape->escapeText($translator->trans('geography.' . strtolower((string) ($area['area_type'] ?? 'administrative_area')))) ?></dd></div><div><dt><?= $escape->escapeText($translator->trans('geography.canonical_code')) ?></dt><dd><?= $escape->escapeText((string) ($area['canonical_code'] ?? '')) ?></dd></div><div><dt><?= $escape->escapeText($translator->trans('geography.result_count')) ?></dt><dd><?= $escape->escapeText((string) count($children)) ?></dd></div></dl>
    <section aria-labelledby="geography-children"><h2 id="geography-children"><?= $escape->escapeText($translator->trans('geography.children')) ?></h2><ul class="link-list"><?php foreach ($children as $child) :
        ?><?php if (is_array($child)) :
    ?><li><?= $escape->escapeText((string) ($child['official_name'] ?? '')) ?> <span class="field-hint">(<?= $escape->escapeText($translator->trans('geography.' . strtolower((string) ($child['area_type'] ?? 'administrative_area')))) ?>)</span></li><?php
        endif; ?><?php
                                                                              endforeach; ?></ul></section>
</article>
