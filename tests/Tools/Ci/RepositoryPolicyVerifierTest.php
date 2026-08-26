<?php

declare(strict_types=1);

namespace Qmdb\Tests\Tools\Ci;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qmdb\Tools\Ci\RepositoryPolicyVerifier;
use Qmdb\Tools\Policy\PathPolicy;

final class RepositoryPolicyVerifierTest extends TestCase
{
    #[DataProvider('forbiddenRepositoryPaths')]
    public function testRepositoryPathPolicyRejectsSensitiveOrGeneratedFiles(string $path): void
    {
        self::assertNotNull((new PathPolicy())->repositoryViolation($path));
    }

    public function testDocumentedEnvironmentTemplateIsAllowed(): void
    {
        self::assertNull((new PathPolicy())->repositoryViolation('.env.example'));
    }

    public function testRepositoryPolicyAcceptsTheCompletedP1CloseoutState(): void
    {
        $report = (new RepositoryPolicyVerifier(dirname(__DIR__, 3)))->verify();

        self::assertTrue($report->passed(), implode("\n", $report->errors()));
    }

    /** @return iterable<string, array{string}> */
    public static function forbiddenRepositoryPaths(): iterable
    {
        yield 'secret environment' => ['.env'];
        yield 'private key' => ['config/application.pem'];
        yield 'vendor' => ['vendor/autoload.php'];
        yield 'node modules' => ['node_modules/jsdom/index.js'];
        yield 'database dump' => ['backup.sql.gz'];
    }
}
