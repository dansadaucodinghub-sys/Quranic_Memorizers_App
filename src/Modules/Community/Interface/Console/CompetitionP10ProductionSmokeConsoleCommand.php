<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Interface\Console;

use Qmdb\Modules\Community\Infrastructure\Persistence\MySqlCommunityFoundationVerificationRepository;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;
use Throwable;

/** Isolated read-only smoke: no content, moderation decision, notification, or external provider mutation. */
final readonly class CompetitionP10ProductionSmokeConsoleCommand implements ConsoleCommand
{
    public function __construct(private MySqlCommunityFoundationVerificationRepository $inventory)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('competition:p10:production-smoke:verify');
    }

    public function description(): string
    {
        return 'Run an isolated, read-only P10 runtime and query-plan smoke.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        try {
            $this->inventory->verify();
            $index = $this->inventory->verifyFeedQueryPlan();
            $output->write("Competition P10 production-like smoke: PASS\n"
                . "Mode: isolated read-only contract smoke\n"
                . "Feed query index: {$index}\n");
            return 0;
        } catch (Throwable $error) {
            $output->write("Competition P10 production-like smoke: FAIL\n{$error->getMessage()}\n");
            return 1;
        }
    }
}
