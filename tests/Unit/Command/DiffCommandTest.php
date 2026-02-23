<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Command;

use Doctrine\Migrations\DependencyFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ManagerRegistry;
use Hakam\MultiTenancyBundle\Attribute\TenantShared;
use Hakam\MultiTenancyBundle\Command\DiffCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DiffCommandTest extends TestCase
{
    private ManagerRegistry&MockObject $registry;
    private ContainerInterface&MockObject $container;
    private EventDispatcherInterface&MockObject $eventDispatcher;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->container = $this->createMock(ContainerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * Creates a testable DiffCommand where Doctrine internals are stubbed:
     * - getDependencyFactory() returns the provided stub factory.
     * - runDiffCommand() is a no-op.
     * - clearTenantEmMetadata() is a no-op.
     *
     * The tenant EM mock returns $metadataClasses from getAllMetadata().
     */
    private function makeCommand(array $metadataClasses = []): DiffCommand
    {
        // Build mocks in TestCase scope (createMock is a TestCase method).
        $mockMetadataFactory = $this->createMock(ClassMetadataFactory::class);
        $mockMetadataFactory->method('getAllMetadata')->willReturn($metadataClasses);

        $mockEm = $this->createMock(EntityManagerInterface::class);
        $mockEm->method('getMetadataFactory')->willReturn($mockMetadataFactory);

        $this->registry->method('getManager')->with('tenant')->willReturn($mockEm);

        $stubFactory = $this->createStub(DependencyFactory::class);

        // Create an anonymous subclass that:
        // 1. Injects the stub factory via a public property (set after construction).
        // 2. Skips real Doctrine execution in overridden methods.
        $command = new class(
            $this->registry,
            $this->container,
            $this->eventDispatcher,
        ) extends DiffCommand {
            public DependencyFactory $testFactory;

            protected function getDependencyFactory(InputInterface $input): DependencyFactory
            {
                return $this->testFactory;
            }

            protected function runDiffCommand(
                DependencyFactory $factory,
                InputInterface $newInput,
                OutputInterface $output,
            ): void {
                // no-op
            }

            protected function clearTenantEmMetadata(EntityManagerInterface $em): void
            {
                // no-op
            }
        };

        $command->testFactory = $stubFactory;
        return $command;
    }

    private function makeClassMetadata(string $entityClass): ClassMetadata&MockObject
    {
        $reflection = new \ReflectionClass($entityClass);
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->isMappedSuperclass = false;
        $metadata->method('getReflectionClass')->willReturn($reflection);
        return $metadata;
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    public function testSharedEntityIsMarkedAsMappedSuperclass(): void
    {
        $metadata = $this->makeClassMetadata(StubSharedForDiff::class);

        $command = $this->makeCommand([$metadata]);
        $command->run(new StringInput(''), new BufferedOutput());

        $this->assertTrue($metadata->isMappedSuperclass);
    }

    public function testNonSharedEntityIsNotModified(): void
    {
        $metadata = $this->makeClassMetadata(StubTenantEntityForDiff::class);

        $command = $this->makeCommand([$metadata]);
        $command->run(new StringInput(''), new BufferedOutput());

        $this->assertFalse($metadata->isMappedSuperclass);
    }

    public function testMixedEntitiesOnlySharedIsHidden(): void
    {
        $sharedMeta = $this->makeClassMetadata(StubSharedForDiff::class);
        $tenantMeta = $this->makeClassMetadata(StubTenantEntityForDiff::class);

        $command = $this->makeCommand([$sharedMeta, $tenantMeta]);
        $command->run(new StringInput(''), new BufferedOutput());

        $this->assertTrue($sharedMeta->isMappedSuperclass, 'Shared entity should be hidden from tenant diff');
        $this->assertFalse($tenantMeta->isMappedSuperclass, 'Tenant entity should remain visible');
    }

    public function testCommandReturnsSuccess(): void
    {
        $command = $this->makeCommand([]);
        $result = $command->run(new StringInput(''), new BufferedOutput());

        $this->assertSame(0, $result);
    }
}

// ── stub entity classes ───────────────────────────────────────────────────────

#[TenantShared]
class StubSharedForDiff {}

class StubTenantEntityForDiff {}
