<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\Purger;

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Hakam\MultiTenancyBundle\Purger\TenantORMPurgerFactory;
use PHPUnit\Framework\TestCase;

class TenantORMPurgerFactoryTest extends TestCase
{
    private TenantORMPurgerFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new TenantORMPurgerFactory();
    }

    public function testCreateForEntityManagerWithDeleteMode(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $purger = $this->factory->createForEntityManager(
            null,
            $entityManager,
            [],
            false
        );

        $this->assertInstanceOf(ORMPurger::class, $purger);
    }

    public function testCreateForEntityManagerWithTruncateMode(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $purger = $this->factory->createForEntityManager(
            null,
            $entityManager,
            [],
            true
        );

        $this->assertInstanceOf(ORMPurger::class, $purger);
    }

    public function testCreateForEntityManagerWithCustomEmName(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $purger = $this->factory->createForEntityManager(
            'custom_em',
            $entityManager,
            ['excluded_table'],
            false
        );

        $this->assertInstanceOf(ORMPurger::class, $purger);
    }

    public function testCreateForEntityManagerWithExcludedTables(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $excluded = ['table1', 'table2', 'table3'];

        $purger = $this->factory->createForEntityManager(
            null,
            $entityManager,
            $excluded,
            false
        );

        $this->assertInstanceOf(ORMPurger::class, $purger);
    }

    public function testCreateForEntityManagerReturnsNewInstanceEachTime(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $purger1 = $this->factory->createForEntityManager(null, $entityManager);
        $purger2 = $this->factory->createForEntityManager(null, $entityManager);

        $this->assertNotSame($purger1, $purger2);
    }
}
