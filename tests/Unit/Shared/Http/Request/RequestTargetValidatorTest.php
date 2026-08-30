<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Http\Request;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Http\Request\RequestTargetValidator;

#[\PHPUnit\Framework\Attributes\Group('InputFuzz')]
final class RequestTargetValidatorTest extends TestCase
{
    #[DataProvider('validTargets')]
    public function testValidTargetsAreDecodedExactlyOnce(string $target, string $expectedPath): void
    {
        $result = (new RequestTargetValidator())->validate($target);

        self::assertTrue($result->isValid());
        self::assertSame($expectedPath, $result->decodedPath());
    }

    /** @return iterable<string, array{string, string}> */
    public static function validTargets(): iterable
    {
        yield 'root' => ['/', '/'];
        yield 'health' => ['/health/live', '/health/live'];
        yield 'parameter' => ['/records/abc123', '/records/abc123'];
        yield 'query ignored' => ['/health/live?source=test', '/health/live'];
        yield 'encoded unicode' => ['/records/%D8%AD%D9%81%D8%B5', '/records/حفص'];
        yield 'literal Arabic' => ['/records/حفص', '/records/حفص'];
        yield 'single decode' => ['/records/%252F', '/records/%2F'];
    }

    #[DataProvider('invalidTargets')]
    public function testUnsafeTargetsAreRejectedWithoutRetainingRawInput(string $target): void
    {
        $result = (new RequestTargetValidator())->validate($target);

        self::assertFalse($result->isValid());
        self::assertStringNotContainsString($target, serialize($result));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidTargets(): iterable
    {
        yield 'invalid percent' => ['/%ZZ'];
        yield 'encoded slash uppercase' => ['/%2F'];
        yield 'encoded slash lowercase' => ['/%2f'];
        yield 'encoded backslash uppercase' => ['/%5C'];
        yield 'encoded backslash lowercase' => ['/%5c'];
        yield 'literal backslash' => ['/\\admin'];
        yield 'nul' => ["/a\0b"];
        yield 'encoded nul' => ['/%00'];
        yield 'control' => ["/a\x1Fb"];
        yield 'dot segment' => ['/./health'];
        yield 'double dot segment' => ['/../admin'];
        yield 'encoded dot segment' => ['/%2E%2E/admin'];
        yield 'missing slash' => ['health/live'];
        yield 'absolute form' => ['https://example.test/health'];
        yield 'authority form' => ['//example.test/health'];
        yield 'asterisk form' => ['*'];
        yield 'fragment' => ['/health#live'];
        yield 'invalid utf8' => ["/records/%C3%28"];
    }

    public function testValidationIsDeterministic(): void
    {
        $validator = new RequestTargetValidator();

        self::assertEquals($validator->validate('/health/live'), $validator->validate('/health/live'));
        self::assertEquals($validator->validate('/%2F'), $validator->validate('/%2F'));
    }
}
