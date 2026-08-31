<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Application;

use Qmdb\Modules\Geography\Infrastructure\Dataset\NigeriaAdministrativeGeographyDatasetLoader;
use Qmdb\Modules\Geography\Infrastructure\Dataset\NigeriaAdministrativeGeographyDatasetValidator;
use Throwable;

final readonly class GeographyReferenceReadinessCheck
{
    public function __construct(
        private GeographyReferenceReadinessProbe $projection,
        private NigeriaAdministrativeGeographyDatasetLoader $loader,
        private NigeriaAdministrativeGeographyDatasetValidator $validator,
    ) {
    }

    public function isReady(): bool
    {
        try {
            $dataset = $this->loader->load();
            if (!$this->validator->validate($dataset)->isValid()) {
                return false;
            }
            return $this->projection->isActiveProjectionValid();
        } catch (Throwable) {
            return false;
        }
    }
}
