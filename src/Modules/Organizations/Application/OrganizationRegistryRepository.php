<?php
declare(strict_types=1);
namespace Qmdb\Modules\Organizations\Application;
use DateTimeImmutable;
use Qmdb\Modules\Tenancy\Application\TenantContext;
use Qmdb\Modules\Tenancy\Application\TenantScopedRepository;
interface OrganizationRegistryRepository extends TenantScopedRepository
{
 /** @return list<array<string,mixed>> */ public function list(TenantContext $tenant,string $search,int $limit):array;
 /** @return array<string,mixed>|null */ public function organization(TenantContext $tenant,string $publicId,bool $forUpdate=false):?array;
 /** @return list<array<string,mixed>> */ public function classifications():array;
 /** @param array<string,mixed> $input @return array<string,mixed> */ public function create(TenantContext $tenant,int $accountId,string $publicId,string $code,array $input,DateTimeImmutable $now):array;
 /** @param array<string,mixed> $organization @param array<string,mixed> $input @return array<string,mixed> */ public function update(TenantContext $tenant,array $organization,array $input,int $expectedVersion,DateTimeImmutable $now):array;
 /** @param array<string,mixed> $organization */ public function retire(TenantContext $tenant,array $organization,int $expectedVersion,DateTimeImmutable $now):int;
 /** @return list<array<string,mixed>> */ public function units(TenantContext $tenant,array $organization):array;
 /** @return array<string,mixed>|null */ public function unit(TenantContext $tenant,array $organization,string $publicId,bool $forUpdate=false):?array;
 /** @param array<string,mixed> $organization @param array<string,mixed> $input @return array<string,mixed> */ public function createChildUnit(TenantContext $tenant,array $organization,array $input,?array $parent,int $accountId,string $publicId,string $code,DateTimeImmutable $now):array;
 /** @param array<string,mixed> $unit @param array<string,mixed> $input @return array<string,mixed> */ public function updateUnit(TenantContext $tenant,array $organization,array $unit,array $input,int $expectedVersion,DateTimeImmutable $now):array;
 /** @param array<string,mixed> $unit */ public function retireUnit(TenantContext $tenant,array $organization,array $unit,int $expectedVersion,DateTimeImmutable $now):void;
 /** @return array{organizations:int,units:int,invalid_rows:int} */ public function report():array;
}
