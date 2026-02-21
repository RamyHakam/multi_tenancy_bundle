<?php

/**
 * Example 12: Testing with TenantTestTrait
 *
 * The bundle provides TenantTestTrait for PHPUnit tests.
 * It simplifies switching tenants and cleaning up state between tests.
 */

namespace App\Tests\Functional;

use App\Entity\Tenant\Product;
use Hakam\MultiTenancyBundle\Doctrine\ORM\TenantEntityManager;
use Hakam\MultiTenancyBundle\Test\TenantTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProductServiceTest extends KernelTestCase
{
    use TenantTestTrait;

    protected function setUp(): void
    {
        self::bootKernel();
    }

    // ──────────────────────────────────────────────
    // runInTenant() — execute code in a tenant context
    // with automatic cleanup afterward
    // ──────────────────────────────────────────────

    public function testCreateProductInTenant(): void
    {
        $result = $this->runInTenant('42', function () {
            $em = $this->getTenantEntityManager();

            $product = new Product();
            $product->setName('Test Product');
            $product->setPrice('19.99');
            $em->persist($product);
            $em->flush();

            return $product->getId();
        });

        $this->assertNotNull($result);

        // After runInTenant(), tenant state is automatically reset.
        // The next call can safely use a different tenant.
    }

    // ──────────────────────────────────────────────
    // Test data isolation between tenants
    // ──────────────────────────────────────────────

    public function testTenantDataIsolation(): void
    {
        // Insert data into tenant A
        $this->runInTenant('tenant_a', function () {
            $em = $this->getTenantEntityManager();
            $product = new Product();
            $product->setName('Tenant A Product');
            $product->setPrice('10.00');
            $em->persist($product);
            $em->flush();
        });

        // Verify tenant B doesn't see tenant A's data
        $this->runInTenant('tenant_b', function () {
            $em = $this->getTenantEntityManager();
            $products = $em->getRepository(Product::class)->findAll();

            $this->assertCount(0, $products, 'Tenant B should not see Tenant A data');
        });
    }

    // ──────────────────────────────────────────────
    // Manual switching (when you need more control)
    // ──────────────────────────────────────────────

    public function testManualSwitching(): void
    {
        // Switch to tenant — no automatic cleanup
        $this->switchToTenant('42');

        $em = $this->getTenantEntityManager();
        $products = $em->getRepository(Product::class)->findAll();
        $this->assertIsArray($products);

        // Manually reset state when done
        $this->resetTenantState();
    }
}

// ──────────────────────────────────────────────
// Testing with the SwitchDbEvent directly
// (without TenantTestTrait)
// ──────────────────────────────────────────────

namespace App\Tests\Integration;

use Hakam\MultiTenancyBundle\Event\SwitchDbEvent;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ManualTenantTest extends KernelTestCase
{
    public function testDirectSwitch(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $dispatcher = $container->get('event_dispatcher');

        // Dispatch the switch event directly
        $dispatcher->dispatch(new SwitchDbEvent('42'));

        // Now the tenant entity manager targets tenant 42's database
        $tenantEm = $container->get('tenant_entity_manager');
        $result = $tenantEm->getConnection()->executeQuery('SELECT 1')->fetchOne();
        $this->assertEquals(1, $result);
    }
}
