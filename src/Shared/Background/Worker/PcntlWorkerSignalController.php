<?php

declare(strict_types=1);

namespace Qmdb\Shared\Background\Worker;

use RuntimeException;

final class PcntlWorkerSignalController implements WorkerSignalController
{
    /** @var array<int, callable|int> */
    private array $previousHandlers = [];
    private bool $registered = false;

    public function isSupported(): bool
    {
        return function_exists('pcntl_signal')
            && function_exists('pcntl_signal_get_handler')
            && function_exists('pcntl_async_signals')
            && defined('SIGTERM')
            && defined('SIGINT');
    }

    public function register(WorkerStopController $stopController): void
    {
        if (!$this->isSupported()) {
            throw new RuntimeException('PCNTL signal handling is unavailable.');
        }
        if ($this->registered) {
            throw new RuntimeException('Worker signal handling is already registered.');
        }
        pcntl_async_signals(true);
        foreach ([(int) constant('SIGTERM'), (int) constant('SIGINT')] as $signal) {
            $this->previousHandlers[$signal] = pcntl_signal_get_handler($signal);
            pcntl_signal($signal, static function () use ($stopController): void {
                $stopController->request(BackgroundWorkerStopReason::SIGNAL);
            });
        }
        $this->registered = true;
    }

    public function restore(): void
    {
        foreach ($this->previousHandlers as $signal => $handler) {
            pcntl_signal($signal, $handler);
        }
        $this->previousHandlers = [];
        $this->registered = false;
    }
}
