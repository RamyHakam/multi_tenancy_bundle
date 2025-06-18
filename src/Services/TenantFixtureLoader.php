<?php

namespace Hakam\MultiTenancyBundle\Services;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Traversable;

final  class TenantFixtureLoader
{
    public function __construct(
        #[AutowireIterator('tenant_fixture')]private readonly iterable $fixtures
    )
    {
    }

    public function loadFixtures(EntityManagerInterface $tenantEntityManager): void
    {
        if (!class_exists(Loader::class)) {
            throw new \LogicException('doctrine/data-fixtures must be installed to use fixture loading.');
        }
        $loader = new Loader();

        foreach ($this->fixtures as $fixture) {
            $loader->addFixture($fixture);
        }
        $executor = new ORMExecutor($tenantEntityManager, new ORMPurger());
        $executor->execute($loader->getFixtures(), true);
    }

    public function getFixtures(): Traversable
    {
        return $this->fixtures;
    }
}
