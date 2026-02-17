<?php

namespace Hakam\MultiTenancyBundle\Tests\Unit\DependencyInjection\Compiler;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Hakam\MultiTenancyBundle\Attribute\MainFixture;
use Hakam\MultiTenancyBundle\Attribute\TenantFixture;
use Hakam\MultiTenancyBundle\DependencyInjection\Compiler\FixtureTaggingPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class FixtureTaggingPassTest extends TestCase
{
    private FixtureTaggingPass $pass;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->pass = new FixtureTaggingPass();
        $this->container = new ContainerBuilder();
    }

    public function testProcessAddsMainFixtureTag(): void
    {
        $definition = new Definition(MainFixtureExample::class);
        $definition->addTag('doctrine.fixture.orm');
        
        $this->container->setDefinition('test.main_fixture', $definition);

        $this->pass->process($this->container);

        $this->assertTrue($definition->hasTag('main_fixture'));
        $this->assertTrue($definition->hasTag('doctrine.fixture.orm'));
    }

    public function testProcessAddsTenantFixtureTagAndRemovesDoctrineTag(): void
    {
        $definition = new Definition(TenantFixtureExample::class);
        $definition->addTag('doctrine.fixture.orm');
        
        $this->container->setDefinition('test.tenant_fixture', $definition);

        $this->pass->process($this->container);

        $this->assertTrue($definition->hasTag('tenant_fixture'));
        $this->assertFalse($definition->hasTag('doctrine.fixture.orm'));
    }

    public function testProcessIgnoresNonFixtureClasses(): void
    {
        $definition = new Definition(NonFixtureClass::class);
        $definition->addTag('doctrine.fixture.orm');
        
        $this->container->setDefinition('test.non_fixture', $definition);

        $originalTags = $definition->getTags();
        
        $this->pass->process($this->container);

        $this->assertSame($originalTags, $definition->getTags());
    }

    public function testProcessSkipsDefinitionsWithoutClass(): void
    {
        $definition = new Definition();
        $definition->addTag('doctrine.fixture.orm');
        
        $this->container->setDefinition('test.no_class', $definition);

        $this->pass->process($this->container);

        // Should not throw exception
        $this->assertTrue(true);
    }

    public function testProcessSkipsNonExistentClasses(): void
    {
        $definition = new Definition('NonExistentClass\\That\\DoesNotExist');
        $definition->addTag('doctrine.fixture.orm');
        
        $this->container->setDefinition('test.non_existent', $definition);

        $this->pass->process($this->container);

        // Should not throw exception
        $this->assertTrue(true);
    }

    public function testProcessHandlesMultipleFixtures(): void
    {
        $mainDef = new Definition(MainFixtureExample::class);
        $mainDef->addTag('doctrine.fixture.orm');
        
        $tenantDef = new Definition(TenantFixtureExample::class);
        $tenantDef->addTag('doctrine.fixture.orm');
        
        $this->container->setDefinition('test.main', $mainDef);
        $this->container->setDefinition('test.tenant', $tenantDef);

        $this->pass->process($this->container);

        $this->assertTrue($mainDef->hasTag('main_fixture'));
        $this->assertTrue($tenantDef->hasTag('tenant_fixture'));
        $this->assertFalse($tenantDef->hasTag('doctrine.fixture.orm'));
    }

    public function testProcessHandlesFixtureWithBothAttributes(): void
    {
        $definition = new Definition(BothAttributesFixture::class);
        $definition->addTag('doctrine.fixture.orm');
        
        $this->container->setDefinition('test.both', $definition);

        $this->pass->process($this->container);

        $this->assertTrue($definition->hasTag('main_fixture'));
        $this->assertTrue($definition->hasTag('tenant_fixture'));
        $this->assertFalse($definition->hasTag('doctrine.fixture.orm'));
    }

    public function testProcessWithEmptyContainer(): void
    {
        $this->pass->process($this->container);

        // Should not throw exception
        $this->assertTrue(true);
    }

    public function testProcessIgnoresServicesWithoutDoctrineFixtureTag(): void
    {
        $definition = new Definition(MainFixtureExample::class);
        // No doctrine.fixture.orm tag
        
        $this->container->setDefinition('test.no_tag', $definition);

        $this->pass->process($this->container);

        $this->assertFalse($definition->hasTag('main_fixture'));
        $this->assertFalse($definition->hasTag('tenant_fixture'));
    }
}

// Test fixtures
#[MainFixture]
class MainFixtureExample extends Fixture
{
    public function load(\Doctrine\Persistence\ObjectManager $manager): void
    {
    }
}

#[TenantFixture]
class TenantFixtureExample extends Fixture
{
    public function load(\Doctrine\Persistence\ObjectManager $manager): void
    {
    }
}

#[MainFixture]
#[TenantFixture]
class BothAttributesFixture extends Fixture
{
    public function load(\Doctrine\Persistence\ObjectManager $manager): void
    {
    }
}

class NonFixtureClass
{
}
