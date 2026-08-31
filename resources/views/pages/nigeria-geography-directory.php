<?php

declare(strict_types=1);

use Qmdb\Shared\Presentation\View\ViewData;

$country = $view->array('country');
$areas = $view->list('areas');
$search = $view->list('search');
$query = $view->string('search_query');
?>
<article class="shell prose-page geography-directory">
    <p class="eyebrow">QMDB</p>
    <h1><?= $escape->escapeText($translator->trans('geography.directory_heading')) ?></h1>
    <p class="lead"><?= $escape->escapeText($translator->trans('geography.directory_intro')) ?></p>
    <p><?= $escape->escapeText($translator->trans('geography.official_names_note')) ?></p>
    <dl class="facts"><div><dt><?= $escape->escapeText($translator->trans('geography.country')) ?></dt><dd><?= $escape->escapeText((string) ($country['common_name'] ?? 'Nigeria')) ?></dd></div><div><dt><?= $escape->escapeText($translator->trans('geography.result_count')) ?></dt><dd><?= $escape->escapeText((string) count($areas)) ?></dd></div></dl>
    <form method="get" action="/locations/nigeria" class="stacked-form">
        <label for="geography-search"><?= $escape->escapeText($translator->trans('geography.search_locations')) ?></label>
        <input id="geography-search" name="q" type="search" maxlength="80" value="<?= $escape->escapeAttribute($query) ?>" autocomplete="off">
        <button class="button" type="submit"><?= $escape->escapeText($translator->trans('geography.search')) ?></button>
    </form>
    <?php if ($query !== '') : ?>
        <section aria-labelledby="geography-search-results"><h2 id="geography-search-results"><?= $escape->escapeText($translator->trans('geography.search_results')) ?></h2><p><?= $escape->escapeText($translator->trans('geography.result_count')) ?>: <?= $escape->escapeText((string) count($search)) ?></p>
            <ul><?php foreach ($search as $area) :
                ?><?php if (is_array($area)) :
    ?><li><?= $escape->escapeText((string) ($area['official_name'] ?? '')) ?> <span class="field-hint">(<?= $escape->escapeText($translator->trans('geography.' . strtolower((string) ($area['area_type'] ?? 'administrative_area')))) ?>)</span></li><?php
                endif; ?><?php
                endforeach; ?></ul>
        </section>
    <?php endif; ?>
    <section aria-labelledby="nigeria-level-one-areas"><h2 id="nigeria-level-one-areas"><?= $escape->escapeText($translator->trans('geography.states_and_fct')) ?></h2>
        <ul class="link-list">
        <?php foreach ($areas as $area) : ?>
            <?php if (!is_array($area) || !is_string($area['canonical_slug'] ?? null) || !is_string($area['official_name'] ?? null) || !is_string($area['area_type'] ?? null)) :
                ?><?php throw new RuntimeException('Geography directory data is invalid.'); ?><?php
            endif; ?>
            <li><a href="/locations/nigeria/<?= $escape->escapeAttribute($area['canonical_slug']) ?>?lang=<?= $escape->escapeAttribute($translator->locale()->value()) ?>"><?= $escape->escapeText($area['official_name']) ?></a> <span class="field-hint">(<?= $escape->escapeText($translator->trans('geography.' . strtolower($area['area_type']))) ?>)</span></li>
        <?php endforeach; ?>
        </ul>
    </section>
    <section aria-labelledby="geography-select-example"><h2 id="geography-select-example"><?= $escape->escapeText($translator->trans('geography.administrative_area')) ?></h2>
        <?= $renderer->render('components.geography-level-one-select', new ViewData(['areas' => $areas, 'selected_public_id' => '']), $translator)->trustedHtml() ?>
        <div id="geography-level-two-region" data-qmdb-geography-child-region aria-live="polite" aria-busy="false"><p><?= $escape->escapeText($translator->trans('geography.choose_level_two')) ?></p></div>
    </section>
</article>
