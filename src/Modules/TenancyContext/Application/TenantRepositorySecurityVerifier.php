<?php

declare(strict_types=1);

namespace Qmdb\Modules\TenancyContext\Application;

use Qmdb\Modules\SecurityAuthorization\Domain\Repository\WorkspaceRoleAssignmentRepository;
use Qmdb\Modules\Tenancy\Domain\Repository\WorkspaceMembershipRepository;

/**
 * Verifies the closed set of P2 tenant-owned repositories. Global and
 * account-scoped repositories are explicit rather than implicitly exempt.
 */
final readonly class TenantRepositorySecurityVerifier
{
    /**
     * @var list<array{contract: class-string, contractPath: string, implementation: class-string,
     *     implementationPath: string, methods: list<string>}>
     */
    private const array TENANT_REPOSITORIES = [
        [
            'contract' => WorkspaceMembershipRepository::class,
            'contractPath' => 'src/Modules/Tenancy/Domain/Repository/WorkspaceMembershipRepository.php',
            'implementation' => \Qmdb\Modules\Tenancy\Infrastructure\Persistence\MySqlWorkspaceMembershipRepository::class,
            'implementationPath' => 'src/Modules/Tenancy/Infrastructure/Persistence/MySqlWorkspaceMembershipRepository.php',
            'methods' => ['create', 'forAccount'],
        ],
        [
            'contract' => WorkspaceRoleAssignmentRepository::class,
            'contractPath' => 'src/Modules/SecurityAuthorization/Domain/Repository/WorkspaceRoleAssignmentRepository.php',
            'implementation' => \Qmdb\Modules\SecurityAuthorization\Infrastructure\Persistence\MySqlWorkspaceRoleAssignmentRepository::class,
            'implementationPath' => 'src/Modules/SecurityAuthorization/Infrastructure/Persistence/MySqlWorkspaceRoleAssignmentRepository.php',
            'methods' => [
                'add', 'findActiveAssignment', 'findActiveForMembershipAndRole', 'listActiveForMembership',
                'listActiveForRole', 'revoke', 'countActiveWorkspaceOwners',
            ],
        ],
    ];

    /** @var array<class-string, string> */
    private const array EXPLICIT_GLOBAL_REPOSITORIES = [
        \Qmdb\Modules\Tenancy\Domain\Repository\WorkspaceRepository::class =>
            'src/Modules/Tenancy/Domain/Repository/WorkspaceRepository.php',
        \Qmdb\Modules\TenancyContext\Domain\Repository\SessionTenantContextRepository::class =>
            'src/Modules/TenancyContext/Domain/Repository/SessionTenantContextRepository.php',
        \Qmdb\Modules\TenancyContext\Application\Background\TenantBoundBackgroundContextRepository::class =>
            'src/Modules/TenancyContext/Application/Background/TenantBoundBackgroundContextRepository.php',
    ];

    public function __construct(private string $projectRoot)
    {
    }

    public function verify(): TenantRepositorySecurityVerificationReport
    {
        $errors = [];
        $methodCount = 0;
        foreach (self::TENANT_REPOSITORIES as $repository) {
            $contract = $this->source($repository['contractPath']);
            if (!is_string($contract) || !str_contains($contract, 'extends TenantScopedRepository')) {
                $errors[] = 'Tenant repository marker is missing: ' . $repository['contract'];
                continue;
            }
            $this->verifyContractMethods($contract, $repository['contract'], $repository['methods'], $errors, $methodCount);
            $this->verifyImplementation($repository['implementation'], $repository['implementationPath'], $errors);
        }
        foreach (self::EXPLICIT_GLOBAL_REPOSITORIES as $repository => $path) {
            if (!is_file($this->projectRoot . '/' . $path)) {
                $errors[] = 'Explicit global repository contract is unavailable: ' . $repository;
            }
        }
        $this->verifyAccountScopedInventory($errors);

        return new TenantRepositorySecurityVerificationReport(
            count(self::TENANT_REPOSITORIES),
            $methodCount,
            count(self::EXPLICIT_GLOBAL_REPOSITORIES),
            $errors,
        );
    }

    /** @param list<string> $methods
     * @param list<string> $errors
     */
    private function verifyContractMethods(
        string $source,
        string $contract,
        array $methods,
        array &$errors,
        int &$methodCount,
    ): void {
        foreach ($methods as $method) {
            ++$methodCount;
            $match = preg_match(
                '/public\\s+function\\s+' . preg_quote($method, '/') . '\\s*\\(\\s*([^,)]+)/s',
                $source,
                $parameters,
            );
            if ($match !== 1) {
                $errors[] = 'Tenant repository method lacks an explicit trusted context: '
                    . $contract . '::' . $method;
                continue;
            }
            if (trim($parameters[1]) !== 'TenantContext $context') {
                $errors[] = 'Tenant repository method lacks an explicit trusted context: '
                    . $contract . '::' . $method;
            }
        }
    }

    /** @param class-string $implementation
     * @param list<string> $errors
     */
    private function verifyImplementation(string $implementation, string $path, array &$errors): void
    {
        $source = $this->source($path);
        if (!is_string($source)) {
            $errors[] = 'Tenant repository implementation cannot be inspected: ' . $implementation;

            return;
        }
        if (!str_contains($source, 'workspace_id = :workspace_id')) {
            $errors[] = 'Tenant repository has no exact workspace SQL scope: ' . $implementation;
        }
        if (!str_contains($source, 'workspaceInternalId()')) {
            $errors[] = 'Tenant repository does not derive SQL scope from trusted context: ' . $implementation;
        }
        if (preg_match('/WHERE\s+(?:[a-z_]+\.)?public_id\s*=\s*:public_id/is', $source) === 1) {
            $errors[] = 'Tenant repository contains a public-ID-only lookup path: ' . $implementation;
        }
    }

    /** @param list<string> $errors */
    private function verifyAccountScopedInventory(array &$errors): void
    {
        $source = $this->source('src/Modules/TenancyContext/Infrastructure/Persistence/MySqlSessionTenantContextRepository.php');
        if (
            !is_string($source)
            || !str_contains($source, 'm.user_account_id = :account_id')
            || !str_contains($source, 'LIMIT ')
        ) {
            $errors[] = 'Account workspace inventory is not explicitly account-scoped and bounded.';
        }
    }

    private function source(string $relativePath): string|false
    {
        return file_get_contents($this->projectRoot . '/' . $relativePath);
    }
}
