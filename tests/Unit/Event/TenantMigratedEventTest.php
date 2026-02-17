<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Unit\Event;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Event\TenantMigratedEvent;
use PHPUnit\Framework\TestCase;

class TenantMigratedEventTest extends TestCase
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

        $event = new TenantMigratedEvent('tenant_1', $config, TenantMigratedEvent::TYPE_INIT, 'v2.0');

        $this->assertSame('tenant_1', $event->getTenantIdentifier());
        $this->assertSame($config, $event->getTenantConfig());
        $this->assertSame(TenantMigratedEvent::TYPE_INIT, $event->getMigrationType());
        $this->assertSame('v2.0', $event->getToVersion());
    }

    public function testIsInitialMigration(): void
    {
        $config = $this->createConfig();

        $event = new TenantMigratedEvent('tenant_1', $config, TenantMigratedEvent::TYPE_INIT);

        $this->assertTrue($event->isInitialMigration());
        $this->assertFalse($event->isUpdateMigration());
    }

    public function testIsUpdateMigration(): void
    {
        $config = $this->createConfig();

        $event = new TenantMigratedEvent('tenant_1', $config, TenantMigratedEvent::TYPE_UPDATE);

        $this->assertTrue($event->isUpdateMigration());
        $this->assertFalse($event->isInitialMigration());
    }

    public function testToVersionDefaultsToNull(): void
    {
        $config = $this->createConfig();

        $event = new TenantMigratedEvent('tenant_1', $config, TenantMigratedEvent::TYPE_INIT);

        $this->assertNull($event->getToVersion());
    }

    public function testTypeConstants(): void
    {
        $this->assertSame('init', TenantMigratedEvent::TYPE_INIT);
        $this->assertSame('update', TenantMigratedEvent::TYPE_UPDATE);
    }

    private function createConfig(): TenantConnectionConfigDTO
    {
        return TenantConnectionConfigDTO::fromArgs(
            'tenant_1',
            DriverTypeEnum::MYSQL,
            DatabaseStatusEnum::DATABASE_CREATED,
            'localhost',
            3306,
            'tenant_1_db',
            'root'
        );
    }
}
