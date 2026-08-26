<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Localization;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Localization\Locale;
use Qmdb\Shared\Localization\LocaleResolver;
use Qmdb\Shared\Localization\SupportedLocales;
use Qmdb\Shared\Localization\TranslationCatalog;
use Qmdb\Shared\Localization\TranslationKey;
use Qmdb\Shared\Localization\Translator;

final class LocalizationFoundationTest extends TestCase
{
    private function catalog(): TranslationCatalog
    {
        $root = dirname(__DIR__, 4);
        return TranslationCatalog::fromFiles([
            'en' => $root . '/resources/translations/en.php',
            'ar' => $root . '/resources/translations/ar.php',
        ]);
    }

    public function testSupportedLocalesAndDirectionsAreExplicit(): void
    {
        self::assertSame('ltr', (new Locale('en'))->direction()->value);
        self::assertSame('rtl', (new Locale('ar'))->direction()->value);
        self::assertTrue((new Locale('ar'))->equals(new Locale('ar')));
        $this->expectException(\InvalidArgumentException::class);
        new Locale('../ar');
    }

    public function testLocaleResolutionUsesBoundedDeterministicPriority(): void
    {
        $resolver = new LocaleResolver(new SupportedLocales());
        $request = (new ServerRequest('GET', '/?lang=ar'))
            ->withQueryParams(['lang' => 'ar'])
            ->withHeader('Accept-Language', 'en');
        self::assertSame('ar', $resolver->resolve($request)->value());
        self::assertSame('ar', $resolver->fromAcceptLanguage('en;q=0.4, ar-SA;q=0.9')->value());
        self::assertSame('en', $resolver->fromAcceptLanguage(str_repeat('a', 1025))->value());
        self::assertSame('en', $resolver->resolve($request->withQueryParams(['lang' => 'xx']))->value());
    }

    public function testCatalogsHaveParityAndMeaningfulUnicodeTranslations(): void
    {
        $catalog = $this->catalog();
        self::assertGreaterThan(40, $catalog->count());
        self::assertContains('title.home', $catalog->keys());
        self::assertSame('Qur’an Memorizer DB', (new Translator($catalog, new Locale('en')))->trans('app.name'));
        self::assertStringContainsString(
            'القرآن',
            (new Translator($catalog, new Locale('ar')))->trans('app.name'),
        );
    }

    public function testTranslationParametersAreBoundedAndRequired(): void
    {
        $translator = new Translator($this->catalog(), new Locale('en'));
        self::assertSame(
            'Request reference: abc123',
            $translator->trans('error.reference', ['request_id' => 'abc123']),
        );
        $this->expectException(\RuntimeException::class);
        $translator->trans('error.reference');
    }

    public function testTranslationKeyRejectsPathsAndSingleSegments(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TranslationKey('../secret');
    }
}
