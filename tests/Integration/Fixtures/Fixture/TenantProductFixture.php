<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Fixture;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Hakam\MultiTenancyBundle\Attribute\TenantFixture;
use Hakam\MultiTenancyBundle\Tests\Integration\Fixtures\Entity\TenantProduct;

#[TenantFixture]
class TenantProductFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $product = new TenantProduct();
        $product->setName('Fixture Product');
        $product->setPrice('25.00');
        $manager->persist($product);
        $manager->flush();
    }
}
