<?php

declare(strict_types=1);

namespace Qmdb\Shared\Console\Command;

use Qmdb\Bootstrap\Application;
use Qmdb\Shared\Application\System\GetSystemInformation;
use Qmdb\Shared\Application\System\SystemInformation;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use UnexpectedValueException;

final readonly class AppAboutConsoleCommand implements ConsoleCommand
{
    public function __construct(private Application $application)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('app:about');
    }

    public function description(): string
    {
        return 'Show safe application and runtime information.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        $information = $this->application->queryBus()->ask(new GetSystemInformation());
        if (!$information instanceof SystemInformation) {
            throw new UnexpectedValueException('System-information query returned an invalid result.');
        }
        $lines = [
            'Application Name: ' . $information->applicationName(),
            'Application Code: ' . $information->applicationCode(),
            'Frozen Baseline: ' . $information->frozenBaseline(),
            'Current Phase: ' . $information->currentPhase(),
            'Current Batch: ' . $information->currentBatch(),
            'Development Version: ' . $information->developmentVersion(),
            'Environment: ' . $information->environment(),
            'Debug Mode: ' . ($information->debugEnabled() ? 'enabled' : 'disabled'),
            'Authoritative Timezone: ' . $information->timezone(),
            'Configuration Source: ' . $information->configurationSource(),
            'Current PHP Version: ' . $information->phpVersion(),
            'Runtime Requirements: satisfied',
        ];
        foreach ($lines as $line) {
            $output->writeln($line);
        }

        return 0;
    }
}
