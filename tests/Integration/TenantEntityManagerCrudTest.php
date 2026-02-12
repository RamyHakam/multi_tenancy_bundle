<?php

namespace Hakam\MultiTenancyBundle\Tests\Integration;

use Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Entity\TenantProduct;

class TenantEntityManagerCrudTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTenantSchema();
    }

    public function testPersistAndFlushAssignsId(): void
    {
        $em = $this->getTenantEntityManager();
        $product = new TenantProduct();
        $product->setName('Widget');
        $product->setPrice('9.99');

        $em->persist($product);
        $em->flush();

        $this->assertNotNull($product->getId());
        $this->assertGreaterThan(0, $product->getId());
    }

    public function testFindEntityById(): void
    {
        $em = $this->getTenantEntityManager();
        $product = new TenantProduct();
        $product->setName('Gadget');
        $product->setPrice('29.99');
        $em->persist($product);
        $em->flush();
        $id = $product->getId();

        $em->clear();

        $found = $em->find(TenantProduct::class, $id);
        $this->assertNotNull($found);
        $this->assertSame('Gadget', $found->getName());
        $this->assertEquals(29.99, (float) $found->getPrice());
    }

    public function testUpdateEntity(): void
    {
        $em = $this->getTenantEntityManager();
        $product = new TenantProduct();
        $product->setName('Original');
        $product->setPrice('5.00');
        $em->persist($product);
        $em->flush();
        $id = $product->getId();

        $product->setName('Updated');
        $product->setPrice('15.00');
        $em->flush();
        $em->clear();

        $found = $em->find(TenantProduct::class, $id);
        $this->assertSame('Updated', $found->getName());
        $this->assertEquals(15.00, (float) $found->getPrice());
    }

    public function testRemoveEntity(): void
    {
        $em = $this->getTenantEntityManager();
        $product = new TenantProduct();
        $product->setName('ToDelete');
        $product->setPrice('1.00');
        $em->persist($product);
        $em->flush();
        $id = $product->getId();

        $em->remove($product);
        $em->flush();
        $em->clear();

        $found = $em->find(TenantProduct::class, $id);
        $this->assertNull($found);
    }

    public function testFindAll(): void
    {
        $em = $this->getTenantEntityManager();

        for ($i = 1; $i <= 3; $i++) {
            $product = new TenantProduct();
            $product->setName("Product $i");
            $product->setPrice((string) ($i * 10));
            $em->persist($product);
        }
        $em->flush();

        $all = $em->getRepository(TenantProduct::class)->findAll();
        $this->assertCount(3, $all);
    }

    public function testFindByWithCriteria(): void
    {
        $em = $this->getTenantEntityManager();

        $p1 = new TenantProduct();
        $p1->setName('Alpha');
        $p1->setPrice('10.00');
        $em->persist($p1);

        $p2 = new TenantProduct();
        $p2->setName('Beta');
        $p2->setPrice('20.00');
        $em->persist($p2);

        $p3 = new TenantProduct();
        $p3->setName('Alpha');
        $p3->setPrice('30.00');
        $em->persist($p3);

        $em->flush();

        $results = $em->getRepository(TenantProduct::class)->findBy(['name' => 'Alpha']);
        $this->assertCount(2, $results);
    }

    public function testClearDetachesAllEntities(): void
    {
        $em = $this->getTenantEntityManager();
        $product = new TenantProduct();
        $product->setName('Managed');
        $product->setPrice('5.00');
        $em->persist($product);
        $em->flush();

        $this->assertTrue($em->contains($product));

        $em->clear();

        $this->assertFalse($em->contains($product));
    }

    public function testTransactionRollback(): void
    {
        $em = $this->getTenantEntityManager();

        $em->beginTransaction();
        $product = new TenantProduct();
        $product->setName('Rollback');
        $product->setPrice('99.99');
        $em->persist($product);
        $em->flush();
        $id = $product->getId();
        $em->rollback();
        $em->clear();

        $found = $em->find(TenantProduct::class, $id);
        $this->assertNull($found);
    }

    public function testMultipleEntitiesInSingleFlush(): void
    {
        $em = $this->getTenantEntityManager();

        $products = [];
        for ($i = 0; $i < 5; $i++) {
            $p = new TenantProduct();
            $p->setName("Batch $i");
            $p->setPrice((string) ($i + 1));
            $em->persist($p);
            $products[] = $p;
        }
        $em->flush();

        foreach ($products as $p) {
            $this->assertNotNull($p->getId());
        }

        $all = $em->getRepository(TenantProduct::class)->findAll();
        $this->assertCount(5, $all);
    }

    public function testTenantEntityManagerConnectionIsTenantConnection(): void
    {
        $tenantEM = $this->getTenantEntityManager();
        $tenantConnection = $this->getContainer()->get('doctrine')->getConnection('tenant');
        $this->assertSame($tenantConnection, $tenantEM->getConnection());
    }
}
