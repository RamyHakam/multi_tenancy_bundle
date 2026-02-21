<?php

/**
 * Example 9: Tenant Fixtures
 *
 * Tenant fixtures seed initial data into each tenant's database.
 * Mark fixture classes with #[TenantFixture] so the bundle knows they
 * should be loaded into tenant databases (not the main database).
 *
 * Load them with: php bin/console tenant:fixtures:load
 */

namespace App\DataFixtures\Tenant;

use App\Entity\Tenant\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Hakam\MultiTenancyBundle\Attribute\TenantFixture;

/**
 * The #[TenantFixture] attribute ensures this fixture is:
 * - NOT loaded by the standard doctrine:fixtures:load command
 * - ONLY loaded by tenant:fixtures:load
 */
#[TenantFixture]
class ProductFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $defaults = [
            ['name' => 'Basic Plan', 'price' => '9.99'],
            ['name' => 'Pro Plan', 'price' => '29.99'],
            ['name' => 'Enterprise Plan', 'price' => '99.99'],
        ];

        foreach ($defaults as $data) {
            $product = new Product();
            $product->setName($data['name']);
            $product->setPrice($data['price']);
            $manager->persist($product);
        }

        $manager->flush();
    }
}

/*
# Load fixtures into a specific tenant database:
php bin/console tenant:fixtures:load 42

# Load fixtures into ALL migrated tenant databases:
php bin/console tenant:fixtures:load

# Append fixtures without purging existing data:
php bin/console tenant:fixtures:load --append

# Load only specific fixture groups:
php bin/console tenant:fixtures:load --group=products

# Exclude certain tables from purging:
php bin/console tenant:fixtures:load --purge-exclusions=audit_log --purge-exclusions=settings

# Use TRUNCATE instead of DELETE for purging:
php bin/console tenant:fixtures:load --purge-with-truncate
*/
