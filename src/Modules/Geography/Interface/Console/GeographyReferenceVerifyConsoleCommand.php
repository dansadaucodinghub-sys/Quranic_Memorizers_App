<?php

declare(strict_types=1);

namespace Qmdb\Modules\Geography\Interface\Console;

use Qmdb\Modules\Geography\Application\GeographyReferenceReadinessCheck;
use Qmdb\Modules\Geography\Infrastructure\Dataset\NigeriaAdministrativeGeographyDatasetLoader;
use Qmdb\Modules\Geography\Infrastructure\Dataset\NigeriaAdministrativeGeographyDatasetValidator;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use RuntimeException;

final readonly class GeographyReferenceVerifyConsoleCommand implements ConsoleCommand
{
    public function __construct(
        private NigeriaAdministrativeGeographyDatasetLoader $loader,
        private NigeriaAdministrativeGeographyDatasetValidator $validator,
        private GeographyReferenceReadinessCheck $readiness,
    ) {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('reference:geography:verify');
    }

    public function description(): string
    {
        return 'Verify the local Nigeria administrative geography reference dataset and database projection.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        $dataset = $this->loader->load();
        $report = $this->validator->validate($dataset);
        if (!$report->isValid() || !$this->readiness->isReady()) {
            $output->write('Geography reference verification: FAIL' . PHP_EOL);
            foreach (array_slice($report->errors(), 0, 8) as $error) {
                $output->write('- ' . $error . PHP_EOL);
            }
            return 1;
        }
        $metadata = $dataset->metadata();
        $datasetCode = $this->requiredString($metadata, 'dataset_code');
        $datasetVersion = $this->requiredString($metadata, 'dataset_version');
        $checksum = $this->requiredString($metadata, 'content_sha256');
        $output->write(sprintf(
            "Geography reference verification: PASS\nDataset: %s v%s\nChecksum: %s\nAreas: %d (states %d, FCT %d, LGAs %d, Area Councils %d)\n",
            $datasetCode,
            $datasetVersion,
            $checksum,
            $this->requiredCount($metadata, 'administrative_areas'),
            $this->requiredCount($metadata, 'states'),
            $this->requiredCount($metadata, 'federal_capital_territories'),
            $this->requiredCount($metadata, 'local_government_areas'),
            $this->requiredCount($metadata, 'area_councils'),
        ));

        return 0;
    }

    /** @param array<string, mixed> $metadata */
    private function requiredString(array $metadata, string $key): string
    {
        $value = $metadata[$key] ?? null;
        if (!is_string($value)) {
            throw new RuntimeException('Validated geography metadata is structurally invalid.');
        }

        return $value;
    }

    /** @param array<string, mixed> $metadata */
    private function requiredCount(array $metadata, string $key): int
    {
        $counts = $metadata['counts'] ?? null;
        if (!is_array($counts) || !is_int($counts[$key] ?? null)) {
            throw new RuntimeException('Validated geography counts are structurally invalid.');
        }

        return $counts[$key];
    }
}
