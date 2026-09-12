<?php

declare(strict_types=1);

namespace Qmdb\Modules\SecurityAuthorization\Domain;

use DateTimeImmutable;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationAssuranceLevel;

/** Exact P6 workspace permissions; existing P5 permission meanings are untouched. */
final readonly class CompetitionP6AuthorizationCatalog
{
    /** @var list<array{string,string,string}> */
    private const array PERMISSIONS = [
        ['01a06000-0000-7001-8000-000000000001', 'workspace.competitions.manage_judges', 'MULTI_FACTOR'],
        ['01a06000-0000-7001-8000-000000000002', 'workspace.competitions.manage_rounds', 'MULTI_FACTOR'],
        ['01a06000-0000-7001-8000-000000000003', 'workspace.competitions.manage_rubrics', 'MULTI_FACTOR'],
        ['01a06000-0000-7001-8000-000000000004', 'workspace.competitions.score', 'MULTI_FACTOR'],
        ['01a06000-0000-7001-8000-000000000005', 'workspace.competitions.calculate_results', 'MULTI_FACTOR'],
        ['01a06000-0000-7001-8000-000000000006', 'workspace.competitions.verify_results', 'PHISHING_RESISTANT'],
        ['01a06000-0000-7001-8000-000000000007', 'workspace.competitions.publish_results', 'PHISHING_RESISTANT'],
        ['01a06000-0000-7001-8000-000000000008', 'workspace.competitions.review_appeals', 'PHISHING_RESISTANT'],
    ];

    public static function extend(AuthorizationCatalogBuilder $builder): void
    {
        $time = new DateTimeImmutable('2026-09-11T00:00:00.000000Z');
        foreach (self::PERMISSIONS as [$id, $code, $assurance]) {
            $builder->permission(new PermissionDefinition(PermissionId::fromString($id), new PermissionCode($code), AuthorizationScopeType::WORKSPACE, AuthenticationAssuranceLevel::from($assurance), PermissionStatus::ACTIVE, 'competition.results', 1, $time, $time));
        }
        foreach (['workspace.owner', 'workspace.administrator', 'workspace.competition_manager'] as $role) {
            foreach (self::PERMISSIONS as [, $code]) {
                $builder->map(new RoleCode($role), new PermissionCode($code));
            }
        }
    }
}
