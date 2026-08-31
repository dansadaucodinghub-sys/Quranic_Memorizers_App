<?php

declare(strict_types=1);

namespace Qmdb\Tests\Architecture;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\TenancyContext\Application\TenantRepositorySecurityVerifier;
use Qmdb\Modules\TenancyContext\Interface\Console\TenantRepositorySecurityVerifyConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\BufferedConsoleOutput;

#[Group('SecurityHardening')]
#[Group('TenantIsolation')]
final class P2TenantRepositorySecurityVerifierTest extends TestCase
{
    public function testClosedTenantRepositoryInventoryRequiresTrustedContextAndScopeEvidence(): void
    {
        $report = $this->verifier()->verify();

        self::assertTrue($report->isValid(), implode(', ', $report->errors));
        self::assertSame(2, $report->tenantRepositoryCount);
        self::assertSame(9, $report->tenantRepositoryMethodCount);
        self::assertSame(3, $report->explicitGlobalRepositoryCount);
    }

    public function testCliReportsOnlyBoundedRepositoryCounts(): void
    {
        $output = new BufferedConsoleOutput();
        $exit = (new TenantRepositorySecurityVerifyConsoleCommand($this->verifier()))->execute(
            new ConsoleInput(new ConsoleCommandName('security:tenant-repositories:verify'), []),
            $output,
        );

        self::assertSame(0, $exit);
        self::assertStringContainsString('Tenant repository verification: PASS', $output->standardOutput());
        self::assertStringNotContainsString('workspace_id', strtolower($output->standardOutput()));
        self::assertSame('', $output->standardError());
    }

    private function verifier(): TenantRepositorySecurityVerifier
    {
        return new TenantRepositorySecurityVerifier(dirname(__DIR__, 2));
    }
}
