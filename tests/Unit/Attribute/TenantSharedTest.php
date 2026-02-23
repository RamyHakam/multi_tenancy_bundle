<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Attribute;

use Hakam\MultiTenancyBundle\Attribute\TenantShared;
use PHPUnit\Framework\TestCase;

class TenantSharedTest extends TestCase
{
    public function testConstructorWithExcludeTenants(): void
    {
        $excludeTenants = ['tenant1', 'tenant2'];
        $group = 'shared_group';

        $attribute = new TenantShared($excludeTenants, $group);

        $this->assertEquals($excludeTenants, $attribute->getExcludeTenants());
        $this->assertEquals($group, $attribute->getGroup());
        $this->assertTrue($attribute->hasExclusions());
    }

    public function testConstructorWithEmptyExclusions(): void
    {
        $attribute = new TenantShared();

        $this->assertEquals([], $attribute->getExcludeTenants());
        $this->assertNull($attribute->getGroup());
        $this->assertFalse($attribute->hasExclusions());
    }

    public function testIsAvailableForTenantReturnsTrueForNonExcludedTenant(): void
    {
        $attribute = new TenantShared(['tenant1', 'tenant2']);

        $this->assertTrue($attribute->isAvailableForTenant('tenant3'));
        $this->assertTrue($attribute->isAvailableForTenant('tenant4'));
    }

    public function testIsAvailableForTenantReturnsFalseForExcludedTenant(): void
    {
        $attribute = new TenantShared(['tenant1', 'tenant2']);

        $this->assertFalse($attribute->isAvailableForTenant('tenant1'));
        $this->assertFalse($attribute->isAvailableForTenant('tenant2'));
    }

    public function testIsAvailableForTenantReturnsTrueWhenNoExclusions(): void
    {
        $attribute = new TenantShared();

        $this->assertTrue($attribute->isAvailableForTenant('any_tenant'));
        $this->assertTrue($attribute->isAvailableForTenant('another_tenant'));
    }

    public function testGetExcludeTenantsReturnsCorrectArray(): void
    {
        $excludeTenants = ['tenant1', 'tenant2', 'tenant3'];
        $attribute = new TenantShared($excludeTenants);

        $this->assertEquals($excludeTenants, $attribute->getExcludeTenants());
    }

    public function testHasExclusionsReturnsTrueWhenExclusionsExist(): void
    {
        $attribute = new TenantShared(['tenant1']);

        $this->assertTrue($attribute->hasExclusions());
    }

    public function testHasExclusionsReturnsFalseWhenNoExclusions(): void
    {
        $attribute = new TenantShared([]);

        $this->assertFalse($attribute->hasExclusions());
    }

    public function testGetGroupReturnsCorrectValue(): void
    {
        $group = 'shared_entities';
        $attribute = new TenantShared([], $group);

        $this->assertEquals($group, $attribute->getGroup());
    }

    public function testGetGroupReturnsNullWhenNotSet(): void
    {
        $attribute = new TenantShared();

        $this->assertNull($attribute->getGroup());
    }
}