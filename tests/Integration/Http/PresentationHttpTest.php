<?php

declare(strict_types=1);

namespace Qmdb\Tests\Integration\Http;

use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Presentation\Response\FragmentRequestDetector;
use Qmdb\Tests\Support\Http\HttpTestFactory;
use Qmdb\Tests\Support\Http\ProductionHttpRuntimeFactory;

final class PresentationHttpTest extends TestCase
{
    public function testEnglishHomeIsACompleteSecureServerRenderedPage(): void
    {
        $response = ProductionHttpRuntimeFactory::create()->handle(HttpTestFactory::request('GET', '/'));
        $html = (string) $response->getBody();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('<html lang="en" dir="ltr">', $html);
        self::assertStringContainsString('<main id="main-content"', $html);
        self::assertStringContainsString('id="qmdb-live-region"', $html);
        self::assertStringContainsString('id="qmdb-dialog"', $html);
        self::assertStringContainsString('data-qmdb-theme', $html);
        self::assertStringNotContainsString('onclick=', strtolower($html));
        self::assertMatchesRegularExpression('/<script nonce="[A-Za-z0-9_-]{22,}">/', $html);
        self::assertStringNotContainsString("'unsafe-inline'", $response->getHeaderLine('Content-Security-Policy'));
        self::assertNotSame('', $response->getHeaderLine('X-Request-ID'));
    }

    public function testArabicQueryRendersRtlAndMeaningfulArabic(): void
    {
        $request = HttpTestFactory::request('GET', '/?lang=ar')->withQueryParams(['lang' => 'ar']);
        $response = ProductionHttpRuntimeFactory::create()->handle($request);
        $html = (string) $response->getBody();
        self::assertSame('ar', $response->getHeaderLine('Content-Language'));
        self::assertStringContainsString('<html lang="ar" dir="rtl">', $html);
        self::assertStringContainsString('القرآن', $html);
    }

    public function testAboutNegotiatesFullPageOrSafeDialogFragment(): void
    {
        $runtime = ProductionHttpRuntimeFactory::create();
        $page = $runtime->handle(HttpTestFactory::request('GET', '/system/about'));
        $fragment = $runtime->handle(HttpTestFactory::request('GET', '/system/about')->withHeader(
            'Accept',
            FragmentRequestDetector::MEDIA_TYPE,
        ));
        self::assertStringContainsString('<html', (string) $page->getBody());
        self::assertSame(
            FragmentRequestDetector::MEDIA_TYPE . '; charset=utf-8',
            $fragment->getHeaderLine('Content-Type'),
        );
        self::assertSame('1', $fragment->getHeaderLine('X-QMDB-Fragment'));
        self::assertSame(1, substr_count((string) $fragment->getBody(), 'data-qmdb-fragment-root'));
        self::assertStringNotContainsString('<script', strtolower((string) $fragment->getBody()));
        self::assertStringNotContainsString('<html', strtolower((string) $fragment->getBody()));
    }

    public function testStatusNegotiatesRefreshableFragmentWithoutOperationalDetail(): void
    {
        $response = ProductionHttpRuntimeFactory::create()->handle(
            HttpTestFactory::request('GET', '/system/status')->withHeader(
                'Accept',
                FragmentRequestDetector::MEDIA_TYPE,
            ),
        );
        $html = strtolower((string) $response->getBody());
        self::assertStringContainsString('data-qmdb-refresh-root', $html);
        self::assertStringNotContainsString('migration id', $html);
        self::assertStringNotContainsString('exception', $html);
        self::assertStringNotContainsString('database host', $html);
    }

    public function testSeparateHtmlRequestsReceiveDifferentCspNonces(): void
    {
        $runtime = ProductionHttpRuntimeFactory::create();
        $first = $runtime->handle(HttpTestFactory::request());
        $second = $runtime->handle(HttpTestFactory::request());
        preg_match("/'nonce-([^']+)'/", $first->getHeaderLine('Content-Security-Policy'), $left);
        preg_match("/'nonce-([^']+)'/", $second->getHeaderLine('Content-Security-Policy'), $right);
        self::assertNotEmpty($left[1] ?? null);
        if (!isset($left[1], $right[1])) {
            self::fail('Both responses must contain a CSP nonce.');
        }
        self::assertNotSame($left[1], $right[1]);
    }

    public function testApiAndHealthResponsesRemainJsonWithStrictCsp(): void
    {
        foreach (['/api/v1/system/about', '/health/live', '/health/ready'] as $path) {
            $response = ProductionHttpRuntimeFactory::create()->handle(HttpTestFactory::request('GET', $path));
            self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
            self::assertSame(
                "default-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'",
                $response->getHeaderLine('Content-Security-Policy'),
            );
        }
    }
}
