<?php

declare(strict_types=1);

namespace Qmdb\Modules\Organizations\Application;

use DateTimeImmutable;
use Qmdb\Modules\Tenancy\Application\TenantContext;
use Qmdb\Modules\Tenancy\Application\TenantScopedRepository;

/**
 * @phpstan-type Record array<string, int|string|null|list<string>>
 * @phpstan-type InputData array<string, string|list<string>|null>
 */
interface OrganizationRegistryRepository extends TenantScopedRepository
{
 /** @return list<Record> */ public function list(TenantContext $tenant, string $search, int $limit): array;
 /** @return Record|null */ public function organization(TenantContext $tenant, string $publicId, bool $forUpdate = false): ?array;
 /** @return list<Record> */ public function classifications(): array;
 /**
  * @param InputData $input
  * @return Record
  */
    public function create(TenantContext $tenant, int $accountId, string $publicId, string $code, array $input, DateTimeImmutable $now): array;
 /**
  * @param Record $organization
  * @param InputData $input
  * @return Record
  */
    public function update(TenantContext $tenant, array $organization, array $input, int $expectedVersion, DateTimeImmutable $now): array;
 /** @param Record $organization */ public function retire(TenantContext $tenant, array $organization, int $expectedVersion, DateTimeImmutable $now): int;
 /**
  * @param Record $organization
  * @return list<Record>
  */
    public function units(TenantContext $tenant, array $organization): array;
 /**
  * @param Record $organization
  * @return Record|null
  */
    public function unit(TenantContext $tenant, array $organization, string $publicId, bool $forUpdate = false): ?array;
 /**
  * @param Record $organization
  * @param InputData $input
  * @param Record|null $parent
  * @return Record
  */
    public function createChildUnit(TenantContext $tenant, array $organization, array $input, ?array $parent, int $accountId, string $publicId, string $code, DateTimeImmutable $now): array;
 /**
  * @param Record $organization
  * @param Record $unit
  * @param InputData $input
  * @return Record
  */
    public function updateUnit(TenantContext $tenant, array $organization, array $unit, array $input, int $expectedVersion, DateTimeImmutable $now): array;
 /**
  * @param Record $organization
  * @param Record $unit
  */
    public function retireUnit(TenantContext $tenant, array $organization, array $unit, int $expectedVersion, DateTimeImmutable $now): void;
 /** @return array{organizations:int,units:int,invalid_rows:int} */ public function report(): array;
}
