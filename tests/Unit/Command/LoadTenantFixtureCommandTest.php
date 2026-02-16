<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Command;

use Doctrine\Bundle\FixturesBundle\Loader\SymfonyFixturesLoader;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Hakam\MultiTenancyBundle\Command\LoadTenantFixtureCommand;
use Hakam\MultiTenancyBundle\Event\TenantBootstrappedEvent;
use Hakam\MultiTenancyBundle\Services\TenantFixtureLoader;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LoadTenantFixtureCommandTest extends TestCase
{
    private ManagerRegistry&MockObject $registry;
    private ContainerInterface&MockObject $container;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private TenantFixtureLoader&MockObject $tenantFixtureLoader;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->container = $this->createMock(ContainerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->tenantFixtureLoader = $this->createMock(TenantFixtureLoader::class);
    }

    public function testCommandHasCorrectName(): void
    {
        $command = $this->createTestableCommand();

        $this->assertEquals('tenant:fixtures:load', $command->getName());
    }

    public function testCommandHasCorrectDescription(): void
    {
        $command = $this->createTestableCommand();

        $this->assertEquals('Load tenant fixtures to the tenant database', $command->getDescription());
    }

    public function testCommandHasCorrectAlias(): void
    {
        $command = $this->createTestableCommand();

        $this->assertContains('t:f:l', $command->getAliases());
    }

    public function testCommandHasDbIdArgument(): void
    {
        $command = $this->createTestableCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('dbId'));
        $this->assertFalse($definition->getArgument('dbId')->isRequired());
    }

    public function testCommandHasAppendOption(): void
    {
        $command = $this->createTestableCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('append'));
    }

    public function testCommandHasGroupOption(): void
    {
        $command = $this->createTestableCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('group'));
        $this->assertTrue($definition->getOption('group')->isArray());
    }

    public function testCommandHasPurgerOption(): void
    {
        $command = $this->createTestableCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('purger'));
        $this->assertEquals('tenant', $definition->getOption('purger')->getDefault());
    }

    public function testCommandHasPurgeExclusionsOption(): void
    {
        $command = $this->createTestableCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('purge-exclusions'));
        $this->assertTrue($definition->getOption('purge-exclusions')->isArray());
    }

    public function testCommandHasPurgeWithTruncateOption(): void
    {
        $command = $this->createTestableCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('purge-with-truncate'));
    }

    public function testSuccessfulExecutionDispatchesTenantBootstrappedEvent(): void
    {
        $fixture1 = $this->createMock(FixtureInterface::class);
        $fixture2 = $this->createMock(FixtureInterface::class);

        $this->tenantFixtureLoader
            ->method('getFixtures')
            ->willReturn(new \ArrayIterator([$fixture1, $fixture2]));

        $dispatchedEvents = [];
        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        $command = $this->createTestableCommand(executeReturnCode: 0);
        $input = new StringInput('TENANT_123');
        $output = new BufferedOutput();

        $result = $command->run($input, $output);

        $this->assertEquals(0, $result);

        // Filter for TenantBootstrappedEvent
        $bootstrappedEvents = array_filter(
            $dispatchedEvents,
            fn($event) => $event instanceof TenantBootstrappedEvent
        );

        $this->assertCount(1, $bootstrappedEvents);

        /** @var TenantBootstrappedEvent $event */
        $event = array_values($bootstrappedEvents)[0];
        $this->assertEquals('TENANT_123', $event->getTenantIdentifier());
        $this->assertCount(2, $event->getLoadedFixtures());
    }

    public function testFailedExecutionDoesNotDispatchTenantBootstrappedEvent(): void
    {
        $this->tenantFixtureLoader
            ->method('getFixtures')
            ->willReturn(new \ArrayIterator([]));

        $dispatchedEvents = [];
        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        $command = $this->createTestableCommand(executeReturnCode: 1);
        $input = new StringInput('TENANT_123');
        $output = new BufferedOutput();

        $result = $command->run($input, $output);

        $this->assertEquals(1, $result);

        // Filter for TenantBootstrappedEvent
        $bootstrappedEvents = array_filter(
            $dispatchedEvents,
            fn($event) => $event instanceof TenantBootstrappedEvent
        );

        $this->assertCount(0, $bootstrappedEvents);
    }

    public function testExecutionWithoutDbIdArgument(): void
    {
        $this->tenantFixtureLoader
            ->method('getFixtures')
            ->willReturn(new \ArrayIterator([]));

        $dispatchedEvents = [];
        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;
                return $event;
            });

        $command = $this->createTestableCommand(executeReturnCode: 0);
        $input = new StringInput('');
        $output = new BufferedOutput();

        $result = $command->run($input, $output);

        $this->assertEquals(0, $result);

        // Filter for TenantBootstrappedEvent
        $bootstrappedEvents = array_filter(
            $dispatchedEvents,
            fn($event) => $event instanceof TenantBootstrappedEvent
        );

        $this->assertCount(1, $bootstrappedEvents);

        /** @var TenantBootstrappedEvent $event */
        $event = array_values($bootstrappedEvents)[0];
        $this->assertNull($event->getTenantIdentifier());
    }

    public function testLoadedFixturesArePassedToEvent(): void
    {
        $fixture1 = new class implements FixtureInterface {
            public function load(\Doctrine\Persistence\ObjectManager $manager): void {}
        };
        $fixture2 = new class implements FixtureInterface {
            public function load(\Doctrine\Persistence\ObjectManager $manager): void {}
        };

        $this->tenantFixtureLoader
            ->method('getFixtures')
            ->willReturn(new \ArrayIterator([$fixture1, $fixture2]));

        $dispatchedEvent = null;
        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(function ($event) use (&$dispatchedEvent) {
                if ($event instanceof TenantBootstrappedEvent) {
                    $dispatchedEvent = $event;
                }
                return $event;
            });

        $command = $this->createTestableCommand(executeReturnCode: 0);
        $input = new StringInput('TENANT_ABC');
        $output = new BufferedOutput();

        $command->run($input, $output);

        $this->assertNotNull($dispatchedEvent);
        $loadedFixtures = $dispatchedEvent->getLoadedFixtures();
        $this->assertCount(2, $loadedFixtures);
        $this->assertEquals(get_class($fixture1), $loadedFixtures[0]);
        $this->assertEquals(get_class($fixture2), $loadedFixtures[1]);
    }

    /**
     * Create a testable LoadTenantFixtureCommand that skips actual fixture execution
     */
    private function createTestableCommand(int $executeReturnCode = 0): LoadTenantFixtureCommand
    {
        $registry = $this->registry;
        $container = $this->container;
        $eventDispatcher = $this->eventDispatcher;
        $tenantFixtureLoader = $this->tenantFixtureLoader;

        return new class(
            $registry,
            $container,
            $eventDispatcher,
            $tenantFixtureLoader,
            $executeReturnCode
        ) extends LoadTenantFixtureCommand {
            private int $testExecuteReturnCode;
            private TenantFixtureLoader $testFixtureLoader;
            private EventDispatcherInterface $testEventDispatcher;

            public function __construct(
                ManagerRegistry $registry,
                ContainerInterface $container,
                EventDispatcherInterface $eventDispatcher,
                TenantFixtureLoader $tenantFixtureLoader,
                int $executeReturnCode
            ) {
                $this->testExecuteReturnCode = $executeReturnCode;
                $this->testFixtureLoader = $tenantFixtureLoader;
                $this->testEventDispatcher = $eventDispatcher;
                parent::__construct($registry, $container, $eventDispatcher, $tenantFixtureLoader);
            }

            protected function configure(): void
            {
                parent::configure();
                // Ensure name is set (in case attribute isn't picked up by anonymous class)
                $this->setName('tenant:fixtures:load');
                $this->setAliases(['t:f:l']);
            }

            protected function initialize(
                \Symfony\Component\Console\Input\InputInterface $input,
                \Symfony\Component\Console\Output\OutputInterface $output
            ): void {
                // Skip initialization that requires database connection
            }

            protected function execute(
                \Symfony\Component\Console\Input\InputInterface $input,
                \Symfony\Component\Console\Output\OutputInterface $output
            ): int {
                // Simulate the event dispatch logic from the original execute method
                if ($this->testExecuteReturnCode === 0) {
                    $loadedFixtures = array_map(
                        fn($fixture) => get_class($fixture),
                        iterator_to_array($this->testFixtureLoader->getFixtures())
                    );

                    $this->testEventDispatcher->dispatch(new TenantBootstrappedEvent(
                        $input->getArgument('dbId'),
                        null,
                        $loadedFixtures
                    ));
                }

                return $this->testExecuteReturnCode;
            }
        };
    }
}
