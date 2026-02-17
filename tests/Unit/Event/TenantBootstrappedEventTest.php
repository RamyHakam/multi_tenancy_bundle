<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Unit\Event;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Event\TenantBootstrappedEvent;
use PHPUnit\Framework\TestCase;

class TenantBootstrappedEventTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $config = TenantConnectionConfigDTO::fromArgs(
            'tenant_1',
            DriverTypeEnum::MYSQL,
            DatabaseStatusEnum::DATABASE_CREATED,
            'localhost',
            3306,
            'tenant_1_db',
            'root',
            'secret'
        );
        $fixtures = ['App\\Fixtures\\UserFixture', 'App\\Fixtures\\RoleFixture'];

        $event = new TenantBootstrappedEvent('tenant_1', $config, $fixtures);

        $this->assertSame('tenant_1', $event->getTenantIdentifier());
        $this->assertSame($config, $event->getTenantConfig());
        $this->assertSame($fixtures, $event->getLoadedFixtures());
    }

    public function testEmptyFixturesByDefault(): void
    {
        $config = TenantConnectionConfigDTO::fromArgs(
            'tenant_1',
            DriverTypeEnum::MYSQL,
            DatabaseStatusEnum::DATABASE_CREATED,
            'localhost',
            3306,
            'tenant_1_db',
            'root'
        );

        $event = new TenantBootstrappedEvent('tenant_1', $config);

        $this->assertSame([], $event->getLoadedFixtures());
    }

    public function testNullConfigAllowed(): void
    {
        $event = new TenantBootstrappedEvent('tenant_1');

        $this->assertNull($event->getTenantConfig());
        $this->assertSame([], $event->getLoadedFixtures());
    }
}
