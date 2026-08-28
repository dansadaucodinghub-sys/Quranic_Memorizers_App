<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\SecurityAuthorization;

use PHPUnit\Framework\TestCase;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationCatalogVerificationReport;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationCatalogVerifier;
use Qmdb\Modules\SecurityAuthorization\Application\AuthorizationReadinessCheck;
use Qmdb\Modules\SecurityAuthorization\Domain\AuthorizationCatalogRegistry;
use Qmdb\Modules\SecurityAuthorization\Interface\Console\AuthorizationVerifyConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\BufferedConsoleOutput;
use Qmdb\Tests\Support\Observability\InMemoryEventLogger;
use Qmdb\Tests\Support\SecurityAuthorization\ConfigurableAuthorizationCatalogVerificationRepository;

final class AuthorizationReadinessTest extends TestCase
{
    public function testCleanCatalogIsReadyAndCliReportsOnlyBoundedCounts(): void
    {
        $repository = new ConfigurableAuthorizationCatalogVerificationRepository(
            new AuthorizationCatalogVerificationReport(10, 7, 27, 0, 0, []),
        );
        $logger = new InMemoryEventLogger();
        $verifier = new AuthorizationCatalogVerifier(
            AuthorizationCatalogRegistry::foundational(),
            $repository,
            $logger,
        );
        self::assertTrue((new AuthorizationReadinessCheck($verifier))->isReady());
        $output = new BufferedConsoleOutput();
        $exit = (new AuthorizationVerifyConsoleCommand($verifier))->execute(
            new ConsoleInput(new ConsoleCommandName('security:authorization:verify'), []),
            $output,
        );

        self::assertSame(0, $exit);
        self::assertStringContainsString('Authorization verification: PASS', $output->standardOutput());
        self::assertStringContainsString('Permissions: 10', $output->standardOutput());
        self::assertSame('', $output->standardError());
        self::assertSame(2, $repository->calls);
        self::assertSame('authorization.catalog.verified', $logger->records()[0]['event']);
    }

    public function testMissingSeedAndCatalogDriftAreNotReadyAndCliReturnsNonZeroWithoutPersonalData(): void
    {
        foreach (
            [
            'AUTHORIZATION_SEED_NOT_APPLIED',
            'AUTHORIZATION_PERMISSION_DRIFT',
            'AUTHORIZATION_MAPPING_DRIFT',
            'AUTHORIZATION_ROLE_DRIFT',
            ] as $error
        ) {
            $repository = new ConfigurableAuthorizationCatalogVerificationRepository(
                new AuthorizationCatalogVerificationReport(9, 7, 26, 0, 0, [$error]),
            );
            $logger = new InMemoryEventLogger();
            $verifier = new AuthorizationCatalogVerifier(
                AuthorizationCatalogRegistry::foundational(),
                $repository,
                $logger,
            );
            self::assertFalse((new AuthorizationReadinessCheck($verifier))->isReady());
            $output = new BufferedConsoleOutput();
            $exit = (new AuthorizationVerifyConsoleCommand($verifier))->execute(
                new ConsoleInput(new ConsoleCommandName('security:authorization:verify'), []),
                $output,
            );
            self::assertSame(1, $exit);
            self::assertStringContainsString($error, $output->standardError());
            self::assertStringNotContainsString('@', $output->standardOutput() . $output->standardError());
            self::assertStringNotContainsString('account', strtolower(
                $output->standardOutput() . $output->standardError(),
            ));
            self::assertSame('authorization.catalog.invalid', $logger->records()[0]['event']);
            self::assertSame(2, $repository->calls);
        }
    }
}
