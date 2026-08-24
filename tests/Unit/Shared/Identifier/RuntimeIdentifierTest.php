<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Identifier;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Identifier\RuntimeIdentifier;
use Qmdb\Shared\Identifier\RuntimeIdentifierGenerator;
use Qmdb\Shared\Identifier\SecureRandomRuntimeIdentifierGenerator;
use ReflectionClass;

final class RuntimeIdentifierTest extends TestCase
{
    public function testGeneratedIdentifierIsCanonical(): void
    {
        $identifier = (new SecureRandomRuntimeIdentifierGenerator())->generate();

        self::assertSame(RuntimeIdentifier::LENGTH, strlen($identifier->value()));
        self::assertMatchesRegularExpression('/\A[a-f0-9]{32}\z/', $identifier->value());
    }

    public function testGeneratorImplementsContract(): void
    {
        self::assertInstanceOf(
            RuntimeIdentifierGenerator::class,
            new SecureRandomRuntimeIdentifierGenerator(),
        );
    }

    public function testGeneratedSampleContainsNoDuplicates(): void
    {
        $generator = new SecureRandomRuntimeIdentifierGenerator();
        $values = [];

        for ($index = 0; $index < 16; $index++) {
            $values[] = $generator->generate()->value();
        }

        self::assertCount(16, array_unique($values));
    }

    public function testInvalidRepresentationsAreRejected(): void
    {
        foreach (['', 'ABCDEF0123456789ABCDEF0123456789', 'abc', str_repeat('g', 32)] as $invalid) {
            try {
                RuntimeIdentifier::fromString($invalid);
                self::fail('Invalid runtime identifier must fail.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testEqualityUsesCanonicalValue(): void
    {
        $value = '0123456789abcdef0123456789abcdef';
        $identifier = RuntimeIdentifier::fromString($value);

        self::assertTrue($identifier->equals(RuntimeIdentifier::fromString($value)));
        self::assertFalse(
            $identifier->equals(RuntimeIdentifier::fromString('fedcba9876543210fedcba9876543210')),
        );
    }

    public function testGeneratorUsesSecureRandomnessOnly(): void
    {
        $path = (new ReflectionClass(SecureRandomRuntimeIdentifierGenerator::class))->getFileName();
        self::assertIsString($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        self::assertStringContainsString('random_bytes(', $source);
        self::assertDoesNotMatchRegularExpression('/\b(?:rand|mt_rand|uniqid)\s*\(/', $source);
    }
}
