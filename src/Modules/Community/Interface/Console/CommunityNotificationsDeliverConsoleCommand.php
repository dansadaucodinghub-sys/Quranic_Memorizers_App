<?php

declare(strict_types=1);

namespace Qmdb\Modules\Community\Interface\Console;

use Qmdb\Modules\Community\Application\CommunityNotificationDeliveryService;
use Qmdb\Shared\Console\Command\ConsoleCommand;
use Qmdb\Shared\Console\Command\ConsoleCommandName;
use Qmdb\Shared\Console\Input\ConsoleInput;
use Qmdb\Shared\Console\Output\ConsoleOutput;

final readonly class CommunityNotificationsDeliverConsoleCommand implements ConsoleCommand
{
    public function __construct(private CommunityNotificationDeliveryService $delivery)
    {
    }

    public function name(): ConsoleCommandName
    {
        return new ConsoleCommandName('community:notifications:deliver');
    }

    public function description(): string
    {
        return 'Deliver a bounded batch of due P10 community status notifications.';
    }

    public function execute(ConsoleInput $input, ConsoleOutput $output): int
    {
        $input->assertOnlyOptions([]);
        $result = $this->delivery->deliverDue();
        $output->write("Community notifications processed.\n"
            . "Claimed: {$result->claimed}\nDelivered: {$result->delivered}\n"
            . "Retried: {$result->retried}\nDead-lettered: {$result->deadLettered}\n"
            . "Stale claims: {$result->stale}\n");
        return $result->stale === 0 ? 0 : 1;
    }
}
