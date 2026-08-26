<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Presentation;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Presentation\Html\HtmlEscaper;
use Qmdb\Shared\Presentation\Html\SafeHtml;
use Qmdb\Shared\Presentation\Html\SafeUrl;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;
use Qmdb\Shared\Presentation\Response\FragmentResponseFactory;
use Qmdb\Shared\Presentation\Security\SecureCspNonceGenerator;
use Qmdb\Shared\Presentation\View\ViewData;
use Qmdb\Shared\Presentation\View\ViewName;
use Qmdb\Shared\Presentation\View\ViewRegistry;

final class PresentationFoundationTest extends TestCase
{
    public function testHtmlEscapingIsContextualUnicodeSafe(): void
    {
        $escaper = new HtmlEscaper();
        self::assertSame('&lt;script&gt;&quot;&apos;&amp;', $escaper->escapeText('<script>"\'&'));
        self::assertSame('القرآن', $escaper->escapeAttribute('القرآن'));
        self::assertStringContainsString('�', $escaper->escapeText("\xC3\x28"));
    }

    public function testSafeUrlsRejectExternalAndProtocolRelativeValues(): void
    {
        $url = SafeUrl::applicationRelative('/system/about')->withQuery(['lang' => 'ar']);
        self::assertSame('/system/about?lang=ar', $url->value());
        foreach (['https://example.com', '//example.com', '/\\evil'] as $unsafe) {
            try {
                SafeUrl::applicationRelative($unsafe);
                self::fail('Unsafe URL was accepted.');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testViewNamesAndRegistryRejectUnregisteredPaths(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'qmdb-view-');
        self::assertIsString($file);
        $registry = new ViewRegistry(['pages.test' => $file]);
        self::assertSame($file, $registry->file(new ViewName('pages.test')));
        @unlink($file);
        $this->expectException(\InvalidArgumentException::class);
        new ViewName('pages.../secret');
    }

    public function testViewDataFailsForMissingOrWrongTypedValues(): void
    {
        $view = new ViewData(['name' => 'QMDB']);
        self::assertSame('QMDB', $view->string('name'));
        $this->expectException(\RuntimeException::class);
        $view->string('missing');
    }

    public function testFragmentResponseRequiresOneNonExecutableRoot(): void
    {
        $factory = new Psr17Factory();
        $responses = new FragmentResponseFactory($factory, $factory);
        $safe = SafeHtml::fromTrustedTemplate('<section data-qmdb-fragment-root>Safe</section>');
        $response = $responses->create($safe);
        self::assertSame(
            FragmentRequestDetector::MEDIA_TYPE . '; charset=utf-8',
            $response->getHeaderLine('Content-Type'),
        );
        self::assertSame('1', $response->getHeaderLine('X-QMDB-Fragment'));
        $this->expectException(\RuntimeException::class);
        $unsafe = SafeHtml::fromTrustedTemplate(
            '<section data-qmdb-fragment-root><script></script></section>',
        );
        $responses->create($unsafe);
    }

    public function testCspNoncesAreStrongAndUnique(): void
    {
        $generator = new SecureCspNonceGenerator();
        $first = $generator->generate()->value();
        $second = $generator->generate()->value();
        self::assertNotSame($first, $second);
        self::assertGreaterThanOrEqual(22, strlen($first));
    }
}
