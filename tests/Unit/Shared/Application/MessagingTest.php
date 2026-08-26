<?php

declare(strict_types=1);

namespace Qmdb\Tests\Unit\Shared\Application;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Qmdb\Shared\Application\Command\Command;
use Qmdb\Shared\Application\Command\CommandHandler;
use Qmdb\Shared\Application\Command\CommandHandlerRegistry;
use Qmdb\Shared\Application\Command\CommandRegistration;
use Qmdb\Shared\Application\Command\SynchronousCommandBus;
use Qmdb\Shared\Application\Event\DomainEventHandler;
use Qmdb\Shared\Application\Event\DomainEventSubscriberRegistration;
use Qmdb\Shared\Application\Event\DomainEventSubscriberRegistry;
use Qmdb\Shared\Application\Event\SynchronousDomainEventDispatcher;
use Qmdb\Shared\Application\Exception\DuplicateHandlerRegistrationException;
use Qmdb\Shared\Application\Exception\InvalidMessageHandlerException;
use Qmdb\Shared\Application\Exception\UnhandledMessageException;
use Qmdb\Shared\Application\Handler\HandlerResolver;
use Qmdb\Shared\Application\Query\Query;
use Qmdb\Shared\Application\Query\QueryHandler;
use Qmdb\Shared\Application\Query\QueryHandlerRegistry;
use Qmdb\Shared\Application\Query\QueryRegistration;
use Qmdb\Shared\Application\Query\SynchronousQueryBus;
use Qmdb\Shared\Domain\Event\DomainEvent;
use Qmdb\Shared\Domain\Event\DomainEventName;
use Qmdb\Shared\Identifier\RuntimeIdentifier;
use RuntimeException;
use stdClass;
use Qmdb\Tests\Support\Application\ArrayHandlerResolver;
use Qmdb\Tests\Support\Application\BaseCommand;
use Qmdb\Tests\Support\Application\BaseCommandHandler;
use Qmdb\Tests\Support\Application\DerivedCommand;
use Qmdb\Tests\Support\Application\EventState;
use Qmdb\Tests\Support\Application\FailingCommandHandler;
use Qmdb\Tests\Support\Application\FailingEventHandler;
use Qmdb\Tests\Support\Application\NonCallableCommandHandler;
use Qmdb\Tests\Support\Application\NonCallableEventHandler;
use Qmdb\Tests\Support\Application\NonCallableQueryHandler;
use Qmdb\Tests\Support\Application\RecordingCommandHandler;
use Qmdb\Tests\Support\Application\RecordingEventHandler;
use Qmdb\Tests\Support\Application\RecordingQueryHandler;
use Qmdb\Tests\Support\Application\ReturningCommandHandler;
use Qmdb\Tests\Support\Application\ReturningEventHandler;
use Qmdb\Tests\Support\Application\TestCommand;
use Qmdb\Tests\Support\Application\TestDomainEvent;
use Qmdb\Tests\Support\Application\TestQuery;

final class MessagingTest extends TestCase
{
    public function testCommandDispatchUsesExactClassAndInvokesOnce(): void
    {
        $handler = new RecordingCommandHandler();
        $registry = new CommandHandlerRegistry();
        $registry->register(new CommandRegistration(TestCommand::class, 'handler.command', 'foundation.application'));
        $command = new TestCommand('payload');
        $bus = new SynchronousCommandBus(
            $registry->freeze(),
            new ArrayHandlerResolver(['handler.command' => $handler]),
        );

        $bus->dispatch($command);

        self::assertSame(1, $handler->invocations);
        self::assertSame($command, $handler->received);
    }

    public function testDuplicateAndInvalidCommandRegistrationsAreRejected(): void
    {
        $registry = new CommandHandlerRegistry();
        $registry->register(new CommandRegistration(TestCommand::class, 'handler.one', 'foundation.application'));
        try {
            $registry->register(new CommandRegistration(TestCommand::class, 'handler.two', 'foundation.application'));
            self::fail('Duplicate command registration must fail.');
        } catch (DuplicateHandlerRegistrationException $exception) {
            self::assertStringContainsString(TestCommand::class, $exception->getMessage());
        }

        $this->expectException(InvalidMessageHandlerException::class);
        (new CommandHandlerRegistry())->register(
            new CommandRegistration(stdClass::class, 'handler.invalid', 'foundation.application'),
        );
    }

    public function testUnhandledAndDerivedCommandsDoNotUseFallbackMapping(): void
    {
        $registry = new CommandHandlerRegistry();
        $registry->register(new CommandRegistration(BaseCommand::class, 'handler.base', 'foundation.application'));
        $bus = new SynchronousCommandBus(
            $registry->freeze(),
            new ArrayHandlerResolver(['handler.base' => new BaseCommandHandler()]),
        );

        $this->expectException(UnhandledMessageException::class);
        $bus->dispatch(new DerivedCommand());
    }

    public function testCommandHandlerMustBeCallableAndReturnNull(): void
    {
        $registry = new CommandHandlerRegistry();
        $registry->register(new CommandRegistration(TestCommand::class, 'handler.command', 'foundation.application'));
        $map = $registry->freeze();

        try {
            (new SynchronousCommandBus(
                $map,
                new ArrayHandlerResolver(['handler.command' => new NonCallableCommandHandler()]),
            ))->dispatch(new TestCommand('payload'));
            self::fail('Non-callable command handler must fail.');
        } catch (InvalidMessageHandlerException $exception) {
            self::assertStringContainsString('handler.command', $exception->getMessage());
        }

        $this->expectException(InvalidMessageHandlerException::class);
        (new SynchronousCommandBus(
            $map,
            new ArrayHandlerResolver(['handler.command' => new ReturningCommandHandler()]),
        ))->dispatch(new TestCommand('payload'));
    }

    public function testCommandHandlerFailurePropagatesWithoutRetry(): void
    {
        $handler = new FailingCommandHandler();
        $registry = new CommandHandlerRegistry();
        $registry->register(new CommandRegistration(TestCommand::class, 'handler.command', 'foundation.application'));
        $bus = new SynchronousCommandBus(
            $registry->freeze(),
            new ArrayHandlerResolver(['handler.command' => $handler]),
        );

        try {
            $bus->dispatch(new TestCommand('payload'));
            self::fail('Handler failure must propagate.');
        } catch (RuntimeException $exception) {
            self::assertSame('handler failed', $exception->getMessage());
        }
        self::assertSame(1, $handler->invocations);
    }

    public function testQueryReturnsResultUnchangedWithoutCaching(): void
    {
        $result = new stdClass();
        $handler = new RecordingQueryHandler($result);
        $registry = new QueryHandlerRegistry();
        $registry->register(new QueryRegistration(TestQuery::class, 'handler.query', 'foundation.application'));
        $bus = new SynchronousQueryBus(
            $registry->freeze(),
            new ArrayHandlerResolver(['handler.query' => $handler]),
        );
        $query = new TestQuery('value');

        self::assertSame($result, $bus->ask($query));
        self::assertSame($result, $bus->ask($query));
        self::assertSame(2, $handler->invocations);
        self::assertSame($query, $handler->received);
    }

    public function testDuplicateInvalidAndUnhandledQueriesFailSafely(): void
    {
        $registry = new QueryHandlerRegistry();
        $registry->register(new QueryRegistration(TestQuery::class, 'handler.one', 'foundation.application'));
        try {
            $registry->register(new QueryRegistration(TestQuery::class, 'handler.two', 'foundation.application'));
            self::fail('Duplicate query registration must fail.');
        } catch (DuplicateHandlerRegistrationException $exception) {
            self::assertStringContainsString(TestQuery::class, $exception->getMessage());
        }

        try {
            (new QueryHandlerRegistry())->register(
                new QueryRegistration(stdClass::class, 'handler.invalid', 'foundation.application'),
            );
            self::fail('Invalid query registration must fail.');
        } catch (InvalidMessageHandlerException $exception) {
            self::assertStringContainsString('query class', $exception->getMessage());
        }

        $this->expectException(UnhandledMessageException::class);
        (new SynchronousQueryBus(
            (new QueryHandlerRegistry())->freeze(),
            new ArrayHandlerResolver([]),
        ))->ask(new TestQuery('value'));
    }

    public function testQueryHandlerMarkerAndCallableAreEnforced(): void
    {
        $registry = new QueryHandlerRegistry();
        $registry->register(new QueryRegistration(TestQuery::class, 'handler.query', 'foundation.application'));
        $map = $registry->freeze();

        foreach ([new stdClass(), new NonCallableQueryHandler()] as $invalidHandler) {
            try {
                (new SynchronousQueryBus(
                    $map,
                    new ArrayHandlerResolver(['handler.query' => $invalidHandler]),
                ))->ask(new TestQuery('value'));
                self::fail('Invalid query handler must fail.');
            } catch (InvalidMessageHandlerException $exception) {
                self::assertStringContainsString('handler.query', $exception->getMessage());
            }
        }
    }

    public function testDomainEventNameAndMetadataAreValidated(): void
    {
        $event = TestDomainEvent::create();

        self::assertSame('system.runtime.started', $event->eventName()->value());
        self::assertSame('UTC', $event->occurredAt()->getTimezone()->getName());
        self::assertNotSame('', $event->eventId()->value());

        $this->expectException(\InvalidArgumentException::class);
        new DomainEventName('../event');
    }

    public function testDomainEventSubscribersRunInRegistrationOrder(): void
    {
        $state = new EventState();
        $registry = new DomainEventSubscriberRegistry();
        $registry->register(new DomainEventSubscriberRegistration(
            TestDomainEvent::class,
            'handler.first',
            'foundation.application',
        ));
        $registry->register(new DomainEventSubscriberRegistration(
            TestDomainEvent::class,
            'handler.second',
            'foundation.application',
        ));
        $dispatcher = new SynchronousDomainEventDispatcher(
            $registry->freeze(),
            new ArrayHandlerResolver([
                'handler.first' => new RecordingEventHandler($state, 'first'),
                'handler.second' => new RecordingEventHandler($state, 'second'),
            ]),
        );

        $dispatcher->dispatch(TestDomainEvent::create());
        self::assertSame(['first', 'second'], $state->calls);
    }

    public function testDuplicateSubscriberPairIsRejectedAndNoSubscriberIsValid(): void
    {
        $registry = new DomainEventSubscriberRegistry();
        $registration = new DomainEventSubscriberRegistration(
            TestDomainEvent::class,
            'handler.event',
            'foundation.application',
        );
        $registry->register($registration);
        try {
            $registry->register($registration);
            self::fail('Duplicate subscriber pair must fail.');
        } catch (DuplicateHandlerRegistrationException $exception) {
            self::assertStringContainsString(TestDomainEvent::class, $exception->getMessage());
        }

        $emptyMap = (new DomainEventSubscriberRegistry())->freeze();
        (new SynchronousDomainEventDispatcher(
            $emptyMap,
            new ArrayHandlerResolver([]),
        ))->dispatch(TestDomainEvent::create());
        self::assertSame([], $emptyMap->subscribersFor(TestDomainEvent::class));
    }

    public function testDomainEventDispatchStopsOnFirstFailure(): void
    {
        $state = new EventState();
        $registry = new DomainEventSubscriberRegistry();
        $registry->register(new DomainEventSubscriberRegistration(
            TestDomainEvent::class,
            'handler.failure',
            'foundation.application',
        ));
        $registry->register(new DomainEventSubscriberRegistration(
            TestDomainEvent::class,
            'handler.after',
            'foundation.application',
        ));
        $dispatcher = new SynchronousDomainEventDispatcher(
            $registry->freeze(),
            new ArrayHandlerResolver([
                'handler.failure' => new FailingEventHandler($state),
                'handler.after' => new RecordingEventHandler($state, 'after'),
            ]),
        );

        try {
            $dispatcher->dispatch(TestDomainEvent::create());
            self::fail('Subscriber exception must propagate.');
        } catch (RuntimeException) {
            self::assertSame(['failure'], $state->calls);
        }
    }

    public function testDomainEventHandlerMustBeCallableAndReturnNull(): void
    {
        $registry = new DomainEventSubscriberRegistry();
        $registry->register(new DomainEventSubscriberRegistration(
            TestDomainEvent::class,
            'handler.event',
            'foundation.application',
        ));
        $map = $registry->freeze();

        try {
            (new SynchronousDomainEventDispatcher(
                $map,
                new ArrayHandlerResolver(['handler.event' => new NonCallableEventHandler()]),
            ))->dispatch(TestDomainEvent::create());
            self::fail('Non-callable event handler must fail.');
        } catch (InvalidMessageHandlerException $exception) {
            self::assertStringContainsString('handler.event', $exception->getMessage());
        }

        $this->expectException(InvalidMessageHandlerException::class);
        (new SynchronousDomainEventDispatcher(
            $map,
            new ArrayHandlerResolver(['handler.event' => new ReturningEventHandler()]),
        ))->dispatch(TestDomainEvent::create());
    }
}
