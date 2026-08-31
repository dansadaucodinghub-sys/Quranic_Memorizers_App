<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Modules\Geography;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Qmdb\Modules\Geography\Domain\AdministrativeAreaCode;
use Qmdb\Modules\Geography\Domain\AdministrativeAreaName;
use Qmdb\Modules\Geography\Domain\CanonicalAreaSlug;
use Qmdb\Modules\Geography\Domain\GeographyPublicId;
use Qmdb\Modules\Geography\Infrastructure\Dataset\NigeriaAdministrativeGeographyDatasetLoader;
use Qmdb\Modules\Geography\Infrastructure\Dataset\NigeriaAdministrativeGeographyDatasetValidator;

final class NigeriaAdministrativeGeographyDatasetTest extends TestCase
{
    public function testCanonicalDatasetIsChecksumValidAndHasTheApprovedHierarchyCounts(): void
    {
        $dataset = (new NigeriaAdministrativeGeographyDatasetLoader(dirname(__DIR__, 4)))->load();
        $report = (new NigeriaAdministrativeGeographyDatasetValidator())->validate($dataset);
        $areas = $dataset->areas();
        $fct = array_values(array_filter(
            $areas,
            static fn (array $area): bool => ($area['canonical_code'] ?? null) === 'NG-FC',
        ));
        $fctChildren = array_values(array_filter(
            $areas,
            static fn (array $area): bool => ($area['parent_canonical_code'] ?? null) === 'NG-FC',
        ));

        self::assertTrue($report->isValid(), $report->failureSummary());
        self::assertSame('NG', $dataset->country()['iso_alpha2']);
        self::assertSame(811, count($areas));
        self::assertSame(1, count($fct));
        self::assertCount(6, $fctChildren);
        self::assertSame($dataset->metadata()['content_sha256'], $dataset->contentChecksum());
    }

    public function testPublicValuesRejectMalformedOrUnsafeInputs(): void
    {
        self::assertSame('ng-fc', (new CanonicalAreaSlug('ng-fc'))->value());
        self::assertSame('NG-FC', (new AdministrativeAreaCode('NG-FC'))->value());
        self::assertSame('Federal Capital Territory', (new AdministrativeAreaName('Federal Capital Territory'))->value());
        self::assertSame(
            'c234787d-c2a4-4d5a-989d-d5e0d3b88013',
            (new GeographyPublicId('C234787D-C2A4-4D5A-989D-D5E0D3B88013'))->value(),
        );

        foreach (['NG FC', 'qmdb-ng-l2-ng001001', 'NG_FC'] as $invalid) {
            try {
                new AdministrativeAreaCode($invalid);
                self::fail('Invalid area code was accepted.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
        foreach (['Federal <Capital>', "Abuja\nMunicipal"] as $invalid) {
            try {
                new AdministrativeAreaName($invalid);
                self::fail('Unsafe administrative area name was accepted.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
