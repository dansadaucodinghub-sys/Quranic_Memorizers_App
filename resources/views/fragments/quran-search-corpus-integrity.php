<?php declare(strict_types=1); $corpus = $view->array('corpus'); ?>
<section data-qmdb-fragment-root data-qmdb-form-region aria-labelledby="quran-search-corpus-heading">
    <h2 id="quran-search-corpus-heading">Active search corpus</h2>
    <dl>
        <dt>Canonical release</dt><dd><bdi><?= $escape->escapeText((string) $corpus['release_code']) ?> <?= $escape->escapeText((string) $corpus['release_version']) ?></bdi></dd>
        <dt>Search source</dt><dd><bdi><?= $escape->escapeText((string) $corpus['source_code']) ?> <?= $escape->escapeText((string) $corpus['source_version']) ?></bdi></dd>
        <dt>Artifact</dt><dd><bdi><?= $escape->escapeText((string) $corpus['original_filename']) ?></bdi>; <?= $escape->escapeText((string) $corpus['artifact_byte_size']) ?> bytes</dd>
        <dt>Artifact checksum</dt><dd><code><?= $escape->escapeText((string) $corpus['artifact_sha256']) ?></code></dd>
        <dt>Corpus</dt><dd><bdi><?= $escape->escapeText((string) $corpus['corpus_code']) ?> <?= $escape->escapeText((string) $corpus['corpus_version']) ?></bdi> (<?= $escape->escapeText((string) $corpus['status']) ?>)</dd>
        <dt>Rows</dt><dd><?= $escape->escapeText((string) $corpus['ayah_count']) ?></dd>
        <dt>Corpus checksum</dt><dd><code><?= $escape->escapeText((string) $corpus['simple_text_sha256']) ?></code></dd>
        <dt>Alignment checksum</dt><dd><code><?= $escape->escapeText((string) $corpus['alignment_sha256']) ?></code></dd>
        <dt>Normalization policy</dt><dd><bdi><?= $escape->escapeText((string) $corpus['normalization_policy_version']) ?></bdi></dd>
        <dt>Import tool</dt><dd><bdi><?= $escape->escapeText((string) $corpus['import_tool_version']) ?></bdi></dd>
        <dt>Last validation</dt><dd><?= $escape->escapeText((string) ($corpus['last_validation_result'] ?? 'NONE')) ?><?= isset($corpus['last_validation_at']) ? ' — ' . $escape->escapeText((string) $corpus['last_validation_at']) : '' ?></dd>
    </dl>
    <?php if ($view->boolean('validation_form')): ?>
        <form method="post" action="/platform/quran/releases/<?= $escape->escapeAttribute((string) $corpus['release_public_id']) ?>/search-corpus/validate" data-qmdb-progressive-form>
            <input type="hidden" name="csrf_token" value="<?= $escape->escapeAttribute($view->string('csrf_token')) ?>">
            <input type="hidden" name="submission_id" value="<?= $escape->escapeAttribute($view->string('submission_id')) ?>">
            <input type="hidden" name="expected_version" value="<?= $escape->escapeAttribute((string) $corpus['version']) ?>">
            <p>This re-verifies immutable corpus evidence. It does not import, change, or expose corpus text.</p>
            <button class="button" type="submit">Validate search corpus</button>
        </form>
    <?php else: ?>
        <p><a class="button" href="/platform/quran/releases/<?= $escape->escapeAttribute((string) $corpus['release_public_id']) ?>/search-corpus/validate">Validate search corpus</a></p>
    <?php endif; ?>
</section>
