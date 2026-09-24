<?php

declare(strict_types=1);

$items = $view->array('items');
$cursor = $view->string('next_cursor');
$language = $view->string('language');
$surah = $view->string('surah');
$error = $view->string('error');
$basePath = $view->string('base_path');
?>
<section data-qmdb-fragment-root aria-label="<?= $escape->escapeAttribute($translator->trans('community.feed.title')) ?>">
    <form method="get" action="<?= $escape->escapeAttribute($basePath) ?>" role="search">
        <label for="community-feed-language"><?= $escape->escapeText($translator->trans('community.feed.language')) ?></label>
        <select id="community-feed-language" name="language">
            <option value=""><?= $escape->escapeText($translator->trans('community.feed.all_languages')) ?></option>
            <option value="ar"<?= $language === 'ar' ? ' selected' : '' ?>><?= $escape->escapeText($translator->trans('community.feed.arabic')) ?></option>
            <option value="en"<?= $language === 'en' ? ' selected' : '' ?>><?= $escape->escapeText($translator->trans('community.feed.english')) ?></option>
        </select>
        <label for="community-feed-surah"><?= $escape->escapeText($translator->trans('community.feed.surah')) ?></label>
        <input id="community-feed-surah" name="surah" type="number" min="1" max="114" value="<?= $escape->escapeAttribute($surah) ?>">
        <button type="submit"><?= $escape->escapeText($translator->trans('community.feed.filter')) ?></button>
    </form>
    <?php if ($error !== '') : ?>
        <p role="alert"><?= $escape->escapeText($translator->trans($error)) ?></p>
    <?php elseif ($items === []) : ?>
        <p><?= $escape->escapeText($translator->trans('community.feed.empty')) ?></p>
    <?php else : ?>
        <ol class="community-feed-list">
            <?php foreach ($items as $item) : ?>
                <li>
                    <article class="community-feed-item">
                        <h2><a href="/community/profiles/<?= $escape->escapeAttribute($item['profile_id']) ?>"><bdi><?= $escape->escapeText($item['alias']) ?></bdi></a></h2>
                        <p><?= $escape->escapeText($translator->trans('community.clip.passage')) ?>:
                            <bdi><?= $escape->escapeText((string) $item['surah']) ?>:<?= $escape->escapeText((string) $item['start']) ?>–<?= $escape->escapeText((string) $item['end']) ?></bdi>
                        </p>
                        <p dir="<?= $item['language'] === 'ar' ? 'rtl' : 'ltr' ?>"><?= $escape->escapeText($item['caption']) ?></p>
                        <p><a href="/clips/<?= $escape->escapeAttribute($item['clip_id']) ?>"><?= $escape->escapeText($translator->trans('community.feed.open')) ?></a></p>
                    </article>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <?php if ($cursor !== '') : ?>
        <?php
        $parameters = ['cursor' => $cursor];
        if ($language !== '') {
            $parameters['language'] = $language;
        }
        if ($surah !== '') {
            $parameters['surah'] = $surah;
        }
        ?>
        <p><a href="<?= $escape->escapeAttribute($basePath . '?' . http_build_query($parameters)) ?>" rel="next"><?= $escape->escapeText($translator->trans('community.feed.more')) ?></a></p>
    <?php endif; ?>
</section>
