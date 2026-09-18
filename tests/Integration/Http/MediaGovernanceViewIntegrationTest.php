<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Localization\{Locale, TranslationCatalog, Translator};
use Qmdb\Shared\Presentation\Asset\AssetUrlGenerator;
use Qmdb\Shared\Presentation\Html\HtmlEscaper;
use Qmdb\Shared\Presentation\View\{PhpViewRenderer, ViewData, ViewRegistry};

final class MediaGovernanceViewIntegrationTest extends TestCase
{
    /** @return iterable<string,array{string,string}> */
    public static function forms(): iterable
    {
        foreach (['en','ar'] as $locale) {
            foreach (['approve','reject','hold','release-hold','withdraw-consent','remove','archive','consent-review'] as $action) {
                yield $locale . ':' . $action => [$locale,$action];
            }
        }
    }

    #[DataProvider('forms')]
    public function testFormsRenderWithAssociatedLabelsEscapingAndNonJavascriptFallback(string $locale, string $action): void
    {
        $root = dirname(__DIR__, 3);
        $renderer = new PhpViewRenderer(new ViewRegistry([
            'pages.media-governance' => $root . '/resources/views/pages/media-governance.php',
            'fragments.media-governance' => $root . '/resources/views/fragments/media-governance.php',
        ]), new HtmlEscaper(), new AssetUrlGenerator());
        $translator = new Translator(TranslationCatalog::fromFiles(['en' => $root . '/resources/translations/en.php','ar' => $root . '/resources/translations/ar.php']), new Locale($locale));
        $data = new ViewData(['action' => $action,'error' => 'media.governance.conflict','saved' => false,'records' => [['public_id' => '<script>alert(1)</script>','status' => 'PENDING_MODERATION','version' => 4,'held' => true]],'step_up' => 'MEDIA_HOLD','csrf_token' => 'test-csrf','submission_id' => '019c0000-0000-7000-8000-000000000001','tenant_context_version' => '1','workspace_id' => '019c0000-0000-7000-8000-000000000002']);
        $html = $renderer->render('pages.media-governance', $data, $translator)->trustedHtml();
        self::assertStringNotContainsString('<script', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('method="post"', $html);
        self::assertStringContainsString('data-qmdb-progressive-form', $html);
        self::assertStringContainsString('role="alert"', $html);
        self::assertStringContainsString('href="#reason-code"', $html);
        self::assertStringContainsString('for="reason-code"', $html);
        self::assertStringContainsString('aria-describedby="reason-help"', $html);
        self::assertStringContainsString('<bdi>', $html);
        self::assertSame(1, substr_count($html, '<h1>'));
        self::assertSame(1, substr_count($html, 'name="csrf_token"'));
        self::assertSame(1, substr_count($html, 'name="submission_id"'));
        self::assertSame(1, substr_count($html, 'name="version"'));
        self::assertStringNotContainsString('tabindex="1"', $html);
        self::assertStringNotContainsString('onclick=', $html);
    }
}
