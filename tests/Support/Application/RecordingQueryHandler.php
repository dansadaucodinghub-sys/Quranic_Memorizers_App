<?php

declare(strict_types=1);

namespace Qmdb\Tests\Support\Application;

use Qmdb\Shared\Application\Query\QueryHandler;

final class RecordingQueryHandler implements QueryHandler
{
    public int $invocations = 0;
    public ?TestQuery $received = null;

    public function __construct(private readonly object $result)
    {
    }

    public function __invoke(TestQuery $query): object
    {
        ++$this->invocations;
        $this->received = $query;

        return $this->result;
    }
}
