<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Application;

use Qmdb\Modules\Geography\Domain\Repository\AdministrativeAreaRepository;
use Qmdb\Modules\Geography\Domain\Repository\CountryRepository;
use Qmdb\Modules\Geography\Domain\Repository\GeographyDatasetRepository;

final readonly class NigeriaGeographyDirectoryHandler
{
    public function __construct(
        private CountryRepository $countries,
        private GeographyDatasetRepository $datasets,
        private AdministrativeAreaRepository $areas,
    ) {
    }

    /** @return array{country:array<string,string>,dataset:array<string,int|string>,areas:list<array<string,int|string>>,search:list<array<string,int|string|null>>} */
    public function handle(NigeriaGeographyDirectoryQuery $query): array
    {
        $country = $this->countries->findActiveByIsoAlpha2('NG');
        $dataset = $this->datasets->findActiveForCountry('NG');
        if ($country === null || $dataset === null) {
            throw new \RuntimeException('Nigeria geography reference data is unavailable.');
        }
        $search = mb_strlen(trim($query->search), 'UTF-8') >= 2
            ? $this->areas->searchActiveNigeria(trim($query->search)) : [];

        return [
            'country' => $country,
            'dataset' => $dataset,
            'areas' => $this->areas->listActiveLevelOne('NG'),
            'search' => $search,
        ];
    }
}
