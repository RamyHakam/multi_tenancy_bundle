<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Integration;

use Hakam\MultiTenancyBundle\Attribute\TenantShared;
use PHPUnit\Framework\TestCase;

class TenantSharedAttributeTest extends TestCase
{
    public function testTenantSharedAttributeIsReadableViaReflection(): void
    {
        $refClass = new \ReflectionClass(TenantSharedTestEntity::class);
        $attributes = $refClass->getAttributes(TenantShared::class);

        $this->assertCount(1, $attributes);
        $this->assertSame(TenantShared::class, $attributes[0]->getName());

        $instance = $attributes[0]->newInstance();
        $this->assertFalse($instance->hasExclusions());
        $this->assertNull($instance->getGroup());
    }

    public function testTenantSharedWithExclusionsFiltersCorrectly(): void
    {
        $refClass = new \ReflectionClass(TenantSharedWithExclusionsEntity::class);
        $attributes = $refClass->getAttributes(TenantShared::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertTrue($instance->hasExclusions());
        $this->assertFalse($instance->isAvailableForTenant('tenant_a'));
        $this->assertTrue($instance->isAvailableForTenant('tenant_b'));
        $this->assertSame(['tenant_a'], $instance->getExcludeTenants());
    }

    public function testTenantSharedGrouping(): void
    {
        $refClass = new \ReflectionClass(TenantSharedGroupedEntity::class);
        $attributes = $refClass->getAttributes(TenantShared::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertSame('config', $instance->getGroup());
        $this->assertFalse($instance->hasExclusions());
    }
}

// Test stub classes — not Doctrine entities, just carriers for the attribute

#[TenantShared]
class TenantSharedTestEntity
{
}

#[TenantShared(excludeTenants: ['tenant_a'])]
class TenantSharedWithExclusionsEntity
{
}

#[TenantShared(group: 'config')]
class TenantSharedGroupedEntity
{
}
