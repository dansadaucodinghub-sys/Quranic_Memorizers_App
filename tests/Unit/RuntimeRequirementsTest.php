<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Qmdb\Bootstrap\RuntimeRequirements;
use Qmdb\Bootstrap\RuntimeViolation;

final class RuntimeRequirementsTest extends TestCase
{
    public function testPhpEightPointFiveSatisfiesTheMinimum(): void
    {
        $result = (new RuntimeRequirements())->evaluate('8.5.0', ['json', 'mbstring']);

        self::assertTrue($result->isSatisfied());
        self::assertSame([], $result->violations());
    }

    public function testLowerPhpVersionIsRejected(): void
    {
        $result = (new RuntimeRequirements())->evaluate('8.4.99', ['json', 'mbstring']);

        self::assertFalse($result->isSatisfied());
        self::assertSame('PHP_VERSION_TOO_LOW', $result->violations()[0]->code());
    }

    public function testRequiredExtensionComparisonIsCaseInsensitive(): void
    {
        $result = (new RuntimeRequirements())->evaluate('8.5.1', ['JSON', 'MbString']);

        self::assertTrue($result->isSatisfied());
    }

    public function testMissingExtensionProducesAStructuredViolation(): void
    {
        $result = (new RuntimeRequirements())->evaluate('8.5.0', ['json']);
        $violations = $result->violations();

        self::assertCount(1, $violations);
        self::assertSame(
            [
                'code' => 'MISSING_EXTENSION',
                'message' => 'The mbstring PHP extension is required.',
            ],
            $violations[0]->toArray(),
        );
    }

    public function testMultipleViolationsAreReportedInDeterministicOrder(): void
    {
        $result = (new RuntimeRequirements())->evaluate('8.2.0', []);

        self::assertSame(
            ['PHP_VERSION_TOO_LOW', 'MISSING_EXTENSION', 'MISSING_EXTENSION'],
            array_map(
                static fn (RuntimeViolation $violation): string => $violation->code(),
                $result->violations(),
            ),
        );
        self::assertStringContainsString('json PHP extension', $result->violations()[1]->message());
        self::assertStringContainsString('mbstring PHP extension', $result->violations()[2]->message());
    }

    public function testCurrentRuntimeEvaluationProducesAResult(): void
    {
        $requirements = new RuntimeRequirements();
        $current = $requirements->evaluateCurrentRuntime();
        $explicit = $requirements->evaluate(PHP_VERSION, get_loaded_extensions());

        self::assertSame($explicit->isSatisfied(), $current->isSatisfied());
        self::assertSame(
            array_map(
                static fn (RuntimeViolation $violation): array => $violation->toArray(),
                $explicit->violations(),
            ),
            array_map(
                static fn (RuntimeViolation $violation): array => $violation->toArray(),
                $current->violations(),
            ),
        );
    }

    public function testErrorFormattingDoesNotExposeUnrelatedRuntimeData(): void
    {
        $result = (new RuntimeRequirements())->evaluate('1.0.0-secret-value', []);
        $output = $result->toCliString();

        self::assertStringContainsString('PHP_VERSION_TOO_LOW', $output);
        self::assertStringContainsString('MISSING_EXTENSION', $output);
        self::assertStringNotContainsString('secret-value', $output);
        self::assertStringNotContainsString(__DIR__, $output);
    }
}
