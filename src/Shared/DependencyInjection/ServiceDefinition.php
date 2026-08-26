<?php

declare(strict_types=1);

namespace Qmdb\Shared\DependencyInjection;

final readonly class ServiceDefinition
{
    /** @param list<string> $dependencies */
    private function __construct(
        public string $id,
        public string $ownerModuleId,
        public array $dependencies,
        public ?ServiceFactory $factory,
        public ?object $instance,
    ) {
    }

    public static function instance(string $id, string $ownerModuleId, object $instance): self
    {
        return new self($id, $ownerModuleId, [], null, $instance);
    }

    /** @param list<string> $dependencies */
    public static function factory(
        string $id,
        string $ownerModuleId,
        array $dependencies,
        ServiceFactory $factory,
    ): self {
        return new self($id, $ownerModuleId, $dependencies, $factory, null);
    }

    public function isInstance(): bool
    {
        return $this->instance !== null;
    }
}
