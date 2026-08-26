<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Observability\Logging;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Observability\Logging\LogContextSanitizer;
use Qmdb\Shared\Observability\Logging\SensitiveKeyMatcher;
use Qmdb\Shared\Observability\Logging\SensitiveValueRedactor;
use Qmdb\Tests\Support\Observability\ExplosiveJsonSerializable;
use Qmdb\Tests\Support\Observability\ExplosiveStringable;

final class ContextSanitizationTest extends TestCase
{
    public function testSupportedValuesArePreservedAndDatesBecomeUtc(): void
    {
        $result = (new LogContextSanitizer())->sanitize([
            'null' => null,
            'bool' => true,
            'int' => 12,
            'float' => 3.5,
            'string' => 'safe',
            'time' => new DateTimeImmutable('2026-08-25T12:00:00+02:00'),
        ]);

        self::assertSame(null, $result['null']);
        self::assertTrue($result['bool']);
        self::assertSame(12, $result['int']);
        self::assertSame(3.5, $result['float']);
        self::assertSame('safe', $result['string']);
        self::assertSame('2026-08-25T10:00:00.000000+00:00', $result['time']);
    }

    public function testStringsDepthEntriesAndNonFiniteValuesAreBounded(): void
    {
        $sanitizer = new LogContextSanitizer(2, 2, 20);
        $result = $sanitizer->sanitize([
            'long' => str_repeat('x', 100),
            'nested' => ['deeper' => ['value' => 'hidden']],
            'extra' => 'removed',
        ]);

        self::assertIsString($result['long']);
        self::assertSame(20, mb_strlen($result['long']));
        self::assertIsArray($result['nested']);
        self::assertSame(['[maximum-depth]'], $result['nested']['deeper']);
        self::assertSame('[truncated]', $result['__truncated_entries']);
        self::assertSame('[non-finite-float]', (new LogContextSanitizer())->sanitize(['n' => NAN])['n']);
        self::assertSame('[non-finite-float]', (new LogContextSanitizer())->sanitize(['n' => INF])['n']);
    }

    public function testObjectsResourcesAndRecursiveArraysAreHandledWithoutInvokingObjects(): void
    {
        $stringable = new ExplosiveStringable();
        $json = new ExplosiveJsonSerializable();
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);
        $recursive = [];
        $recursive['self'] = &$recursive;

        try {
            $result = (new LogContextSanitizer())->sanitize([
                'stringable' => $stringable,
                'json' => $json,
                'resource' => $resource,
                'recursive' => $recursive,
            ]);
        } finally {
            fclose($resource);
        }

        self::assertSame('[object:' . ExplosiveStringable::class . ']', $result['stringable']);
        self::assertSame('[object:' . ExplosiveJsonSerializable::class . ']', $result['json']);
        self::assertSame('[resource]', $result['resource']);
        self::assertFalse($stringable->invoked);
        self::assertFalse($json->invoked);
        self::assertIsArray($result['recursive']);
    }

    /** @return iterable<string, array{string}> */
    public static function sensitiveKeys(): iterable
    {
        foreach (
            [
            'password', 'passwd', 'secret', 'token', 'Authorization', 'cookie', 'set-cookie', 'session',
            'csrf', 'api_key', 'apikey', 'private_key', 'signing_key', 'credential', 'database_password',
            'db_password', 'recovery', 'mfa', 'otp', 'nin', 'identity_document',
            ] as $key
        ) {
            yield $key => [$key];
        }
    }

    #[DataProvider('sensitiveKeys')]
    public function testSensitiveKeysAreRecursivelyAndStablyRedacted(string $key): void
    {
        $secret = 'QMDB_UNMISTAKABLE_SECRET_PREFIX_8291_SUFFIX';
        $result = (new SensitiveValueRedactor(new SensitiveKeyMatcher()))->redact([
            'nested' => [$key => $secret],
        ]);

        self::assertIsArray($result['nested']);
        self::assertSame(SensitiveValueRedactor::REDACTED, $result['nested'][$key]);
        self::assertStringNotContainsString($secret, json_encode($result, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('PREFIX', json_encode($result, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('SUFFIX', json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function testSanitizingAndRedactingDoNotMutateInput(): void
    {
        $original = ['password' => 'secret', 'nested' => ['safe' => 'value']];
        $copy = $original;

        (new LogContextSanitizer())->sanitize($original);
        (new SensitiveValueRedactor(new SensitiveKeyMatcher()))->redact($original);

        self::assertSame($copy, $original);
    }
}
