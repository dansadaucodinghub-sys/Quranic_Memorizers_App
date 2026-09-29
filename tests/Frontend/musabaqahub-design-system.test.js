import assert from 'node:assert/strict';
import { readFile, stat } from 'node:fs/promises';
import test from 'node:test';

const read = (path) => readFile(new URL(`../../${path}`, import.meta.url), 'utf8');

test('application shell exposes the MusabaqaHub brand and install metadata', async () => {
    const [layout, header, footer, home, manifestSource] = await Promise.all([
        read('resources/views/layouts/application.php'),
        read('resources/views/components/application-header.php'),
        read('resources/views/components/application-footer.php'),
        read('resources/views/pages/home.php'),
        read('public/site.webmanifest'),
    ]);

    assert.match(layout, /rel="manifest" href="\/site\.webmanifest"/);
    assert.match(layout, /rel="apple-touch-icon"/);
    assert.match(header, /musabaqahub-app-icon\.png/);
    assert.match(header, /class="account-menu"/);
    assert.match(header, /aria-current="page"/);
    assert.match(footer, /brand\.promise/);
    assert.match(home, /musabaqahub-logo\.png/);

    const manifest = JSON.parse(manifestSource);
    assert.equal(manifest.name, 'MusabaqaHub');
    assert.equal(manifest.display, 'standalone');
    assert.equal(manifest.theme_color, '#043f35');
});

test('provided brand assets are installed and the shared design system covers responsive and accessible states', async () => {
    const [logo, icon, tokens, layout, components, utilities] = await Promise.all([
        stat(new URL('../../public/assets/brand/musabaqahub-logo.png', import.meta.url)),
        stat(new URL('../../public/assets/brand/musabaqahub-app-icon.png', import.meta.url)),
        read('public/assets/css/tokens.css'),
        read('public/assets/css/layout.css'),
        read('public/assets/css/components.css'),
        read('public/assets/css/utilities.css'),
    ]);

    assert.ok(logo.size > 100_000);
    assert.ok(icon.size > 100_000);
    assert.match(tokens, /--color-primary-strong:\s*#043f35/);
    assert.match(tokens, /--color-accent:/);
    assert.match(layout, /@media \(max-width: 47\.99rem\)/);
    assert.match(components, /:focus-visible|account-menu/);
    assert.match(utilities, /prefers-reduced-motion/);
    assert.match(utilities, /forced-colors/);
    assert.match(utilities, /@media print/);
});
