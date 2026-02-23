<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Attribute;

use Hakam\MultiTenancyBundle\Attribute\TenantEntity;
use PHPUnit\Framework\TestCase;

class TenantEntityTest extends TestCase
{
    public function testEmptyTenantsIsAvailableToAll(): void
    {
        $attribute = new TenantEntity();

        $this->assertTrue($attribute->isAvailableForTenant('tenant1'));
        $this->assertTrue($attribute->isAvailableForTenant('tenant2'));
        $this->assertTrue($attribute->isAvailableForTenant('any_tenant'));
    }

    public function testListedTenantIsAvailable(): void
    {
        $attribute = new TenantEntity(['tenant1', 'tenant3']);

        $this->assertTrue($attribute->isAvailableForTenant('tenant1'));
        $this->assertTrue($attribute->isAvailableForTenant('tenant3'));
    }

    public function testUnlistedTenantIsNotAvailable(): void
    {
        $attribute = new TenantEntity(['tenant1', 'tenant3']);

        $this->assertFalse($attribute->isAvailableForTenant('tenant2'));
        $this->assertFalse($attribute->isAvailableForTenant('tenant4'));
    }

    public function testIsRestrictedToSpecificTenantsReturnsTrueWhenTenantsListed(): void
    {
        $attribute = new TenantEntity(['tenant1']);

        $this->assertTrue($attribute->isRestrictedToSpecificTenants());
    }

    public function testIsRestrictedToSpecificTenantsReturnsFalseWhenEmpty(): void
    {
        $attribute = new TenantEntity([]);

        $this->assertFalse($attribute->isRestrictedToSpecificTenants());
    }

    public function testGetTenantsReturnsCorrectArray(): void
    {
        $tenants = ['tenant1', 'tenant2', 'tenant3'];
        $attribute = new TenantEntity($tenants);

        $this->assertEquals($tenants, $attribute->getTenants());
    }

    public function testGetTenantsReturnsEmptyArrayByDefault(): void
    {
        $attribute = new TenantEntity();

        $this->assertEquals([], $attribute->getTenants());
    }

    public function testGetGroupReturnsNullByDefault(): void
    {
        $attribute = new TenantEntity();

        $this->assertNull($attribute->getGroup());
    }

    public function testGetGroupReturnsSetValue(): void
    {
        $attribute = new TenantEntity([], 'my_group');

        $this->assertSame('my_group', $attribute->getGroup());
    }

    public function testSingleTenantWhitelistBehavesCorrectly(): void
    {
        $attribute = new TenantEntity(['only_this_one']);

        $this->assertTrue($attribute->isAvailableForTenant('only_this_one'));
        $this->assertFalse($attribute->isAvailableForTenant('anyone_else'));
    }
}
