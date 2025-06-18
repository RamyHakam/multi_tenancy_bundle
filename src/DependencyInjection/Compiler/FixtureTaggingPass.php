<?php

namespace Hakam\MultiTenancyBundle\DependencyInjection\Compiler;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Hakam\MultiTenancyBundle\Attribute\MainFixture;
use Hakam\MultiTenancyBundle\Attribute\TenantFixture;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FixtureTaggingPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $taggedServiceIds = $container->findTaggedServiceIds('doctrine.fixture.orm');

        foreach ($taggedServiceIds as $id => $tags) {
            $definition = $container->getDefinition($id);
            $class = $definition->getClass();

            if (!$class || !class_exists($class)) {
                continue;
            }

            try {
                $refClass = new \ReflectionClass($class);

                if (!is_subclass_of($class, Fixture::class)) {
                    continue;
                }

                if ($refClass->getAttributes(MainFixture::class)) {
                    $definition->addTag('main_fixture');
                }

                if ($refClass->getAttributes(TenantFixture::class)) {
                    $definition->clearTag('doctrine.fixture.orm');
                    $definition->addTag('tenant_fixture');
                }
            } catch (\Throwable) {
                continue; // Skip unresolvable classes
            }
        }
    }
}