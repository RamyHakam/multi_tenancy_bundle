<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Traits;

use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Services\TenantDbConfigurationInterface;
use Hakam\MultiTenancyBundle\Traits\TenantDbConfigTrait;
use Hakam\MultiTenancyBundle\ValueObject\TenantDatabaseIdentifier;
use PHPUnit\Framework\TestCase;

/**
 * An entity is expected to use the trait AND implement the interface, so the
 * two have to agree on every signature — PHP rejects the class outright when
 * they drift, which is a fatal error in the host application rather than a
 * test failure here. Loading this file is the assertion.
 */
class TenantDbConfigTraitTest extends TestCase
{
    public function testTheTraitSatisfiesTheEntityContract(): void
    {
        $entity = new TenantDbConfigFixture();
        $identifier = TenantDatabaseIdentifier::generateWithValue('tenant_db');

        $entity->setTenantIdentifier($identifier);
        $entity->setDbName('tenant_db');
        $entity->setDatabaseStatus(DatabaseStatusEnum::DATABASE_CREATED);

        $this->assertInstanceOf(TenantDbConfigurationInterface::class, $entity);
        $this->assertTrue($identifier->equals($entity->getTenantIdentifier()));
        $this->assertSame(DatabaseStatusEnum::DATABASE_CREATED, $entity->getDatabaseStatus());
    }
}

class TenantDbConfigFixture implements TenantDbConfigurationInterface
{
    use TenantDbConfigTrait;

    public function getId(): ?int
    {
        return 1;
    }
}
