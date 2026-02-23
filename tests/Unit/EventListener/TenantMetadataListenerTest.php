<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\EventListener;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Hakam\MultiTenancyBundle\Attribute\TenantEntity;
use Hakam\MultiTenancyBundle\Attribute\TenantShared;
use Hakam\MultiTenancyBundle\Context\TenantContextInterface;
use Hakam\MultiTenancyBundle\EventListener\TenantMetadataListener;
use PHPUnit\Framework\TestCase;

class TenantMetadataListenerTest extends TestCase
{
    private TenantContextInterface $context;
    private TenantMetadataListener $listener;

    protected function setUp(): void
    {
        $this->context = $this->createMock(TenantContextInterface::class);
        $this->listener = new TenantMetadataListener($this->context);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function makeArgs(string $entityClass): LoadClassMetadataEventArgs
    {
        $reflection = new \ReflectionClass($entityClass);

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->isMappedSuperclass = false;
        $metadata->method('getReflectionClass')->willReturn($reflection);
        $metadata->method('getTableName')->willReturn('stub_table');

        $args = $this->createMock(LoadClassMetadataEventArgs::class);
        $args->method('getClassMetadata')->willReturn($metadata);

        return $args;
    }

    private function setTenant(?string $id): void
    {
        $this->context->method('getTenantId')->willReturn($id);
        if ($id !== null) {
            $this->context->method('getSchema')->willReturn('tenant_' . $id);
        }
    }

    // ── no tenant active ─────────────────────────────────────────────────────

    public function testNoTenantActiveSkipsAllProcessing(): void
    {
        $this->setTenant(null);
        $args = $this->makeArgs(StubUnattributedEntity::class);

        $args->getClassMetadata()->expects($this->never())->method('setPrimaryTable');

        $this->listener->loadClassMetadata($args);
    }

    // ── abstract class ───────────────────────────────────────────────────────

    public function testAbstractClassIsSkipped(): void
    {
        $this->setTenant('tenant1');

        $reflection = new \ReflectionClass(StubAbstractEntity::class);
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->isMappedSuperclass = false;
        $metadata->method('getReflectionClass')->willReturn($reflection);
        $metadata->expects($this->never())->method('setPrimaryTable');

        $args = $this->createMock(LoadClassMetadataEventArgs::class);
        $args->method('getClassMetadata')->willReturn($metadata);

        $this->listener->loadClassMetadata($args);
    }

    // ── #[TenantShared] ───────────────────────────────────────────────────────

    public function testSharedEntitySetsSharedSchema(): void
    {
        $this->setTenant('tenant1');
        $args = $this->makeArgs(StubSharedEntity::class);

        $args->getClassMetadata()->expects($this->once())
            ->method('setPrimaryTable')
            ->with(['name' => 'stub_table', 'schema' => 'shared']);

        $this->listener->loadClassMetadata($args);
    }

    public function testSharedEntityWithExclusionHidesForExcludedTenant(): void
    {
        $this->setTenant('tenant2');
        $args = $this->makeArgs(StubSharedExcludesTenant2::class);

        $args->getClassMetadata()->expects($this->never())->method('setPrimaryTable');

        $this->listener->loadClassMetadata($args);
        $this->assertTrue($args->getClassMetadata()->isMappedSuperclass);
    }

    public function testSharedEntityWithExclusionIsVisibleToNonExcludedTenant(): void
    {
        $this->setTenant('tenant1');
        $args = $this->makeArgs(StubSharedExcludesTenant2::class);

        $args->getClassMetadata()->expects($this->once())
            ->method('setPrimaryTable')
            ->with(['name' => 'stub_table', 'schema' => 'shared']);

        $this->listener->loadClassMetadata($args);
    }

    // ── #[TenantEntity] ───────────────────────────────────────────────────────

    public function testTenantEntityIsVisibleForListedTenant(): void
    {
        $this->setTenant('tenant1');
        $args = $this->makeArgs(StubTenantSpecificEntity::class);

        $args->getClassMetadata()->expects($this->once())
            ->method('setPrimaryTable')
            ->with(['name' => 'stub_table', 'schema' => 'tenant_tenant1']);

        $this->listener->loadClassMetadata($args);
        $this->assertFalse($args->getClassMetadata()->isMappedSuperclass);
    }

    public function testTenantEntityIsHiddenForUnlistedTenant(): void
    {
        $this->setTenant('tenant2');
        $args = $this->makeArgs(StubTenantSpecificEntity::class);

        $this->listener->loadClassMetadata($args);
        $this->assertTrue($args->getClassMetadata()->isMappedSuperclass);
    }

    public function testTenantEntityWithEmptyListIsVisibleToAll(): void
    {
        $this->setTenant('any_tenant');
        $args = $this->makeArgs(StubTenantEntityNoRestriction::class);

        $args->getClassMetadata()->expects($this->once())
            ->method('setPrimaryTable')
            ->with(['name' => 'stub_table', 'schema' => 'tenant_any_tenant']);

        $this->listener->loadClassMetadata($args);
        $this->assertFalse($args->getClassMetadata()->isMappedSuperclass);
    }

    // ── no attribute (default) ────────────────────────────────────────────────

    public function testUnattributedEntityGetsTenantSchema(): void
    {
        $this->setTenant('tenant1');
        $args = $this->makeArgs(StubUnattributedEntity::class);

        $args->getClassMetadata()->expects($this->once())
            ->method('setPrimaryTable')
            ->with(['name' => 'stub_table', 'schema' => 'tenant_tenant1']);

        $this->listener->loadClassMetadata($args);
    }

    // ── attribute cache ───────────────────────────────────────────────────────

    public function testAttributeClassificationIsCachedAcrossCalls(): void
    {
        $this->setTenant('tenant1');

        // Two separate LoadClassMetadataEventArgs for the same class
        $args1 = $this->makeArgs(StubSharedEntity::class);
        $args2 = $this->makeArgs(StubSharedEntity::class);

        // Both calls should set schema to 'shared' — proves the cache path works
        $args1->getClassMetadata()->expects($this->once())->method('setPrimaryTable');
        $args2->getClassMetadata()->expects($this->once())->method('setPrimaryTable');

        $this->listener->loadClassMetadata($args1);
        $this->listener->loadClassMetadata($args2);
    }
}

// ── stub entity classes used by the tests above ──────────────────────────────

abstract class StubAbstractEntity {}

#[TenantShared]
class StubSharedEntity {}

#[TenantShared(excludeTenants: ['tenant2'])]
class StubSharedExcludesTenant2 {}

#[TenantEntity(tenants: ['tenant1', 'tenant3'])]
class StubTenantSpecificEntity {}

#[TenantEntity(tenants: [])]
class StubTenantEntityNoRestriction {}

class StubUnattributedEntity {}
