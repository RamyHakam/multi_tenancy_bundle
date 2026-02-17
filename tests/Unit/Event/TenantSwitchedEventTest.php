<?php

declare(strict_types=1);

namespace Hakam\MultiTenancyBundle\Tests\Unit\Event;

use Hakam\MultiTenancyBundle\Config\TenantConnectionConfigDTO;
use Hakam\MultiTenancyBundle\Enum\DatabaseStatusEnum;
use Hakam\MultiTenancyBundle\Enum\DriverTypeEnum;
use Hakam\MultiTenancyBundle\Event\TenantSwitchedEvent;
use PHPUnit\Framework\TestCase;

class TenantSwitchedEventTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $config = TenantConnectionConfigDTO::fromArgs(
            'tenant_2',
            DriverTypeEnum::MYSQL,
            DatabaseStatusEnum::DATABASE_CREATED,
            'localhost',
            3306,
            'tenant_2_db',
            'root',
            'secret'
        );

        $event = new TenantSwitchedEvent('tenant_2', $config, 'tenant_1', 'tenant_1_db');

        $this->assertSame('tenant_2', $event->getTenantIdentifier());
        $this->assertSame($config, $event->getTenantConfig());
        $this->assertSame('tenant_1', $event->getPreviousTenantIdentifier());
        $this->assertSame('tenant_1_db', $event->getPreviousDatabaseName());
    }

    public function testHadPreviousTenantReturnsTrueWhenSet(): void
    {
        $config = TenantConnectionConfigDTO::fromArgs(
            'tenant_2',
            DriverTypeEnum::MYSQL,
            DatabaseStatusEnum::DATABASE_CREATED,
            'localhost',
            3306,
            'tenant_2_db',
            'root'
        );

        $event = new TenantSwitchedEvent('tenant_2', $config, 'tenant_1', 'tenant_1_db');

        $this->assertTrue($event->hadPreviousTenant());
    }

    public function testHadPreviousTenantReturnsFalseWhenNull(): void
    {
        $config = TenantConnectionConfigDTO::fromArgs(
            'tenant_2',
            DriverTypeEnum::MYSQL,
            DatabaseStatusEnum::DATABASE_CREATED,
            'localhost',
            3306,
            'tenant_2_db',
            'root'
        );

        $event = new TenantSwitchedEvent('tenant_2', $config);

        $this->assertFalse($event->hadPreviousTenant());
    }

    public function testDefaultsForOptionalParams(): void
    {
        $config = TenantConnectionConfigDTO::fromArgs(
            'tenant_2',
            DriverTypeEnum::MYSQL,
            DatabaseStatusEnum::DATABASE_CREATED,
            'localhost',
            3306,
            'tenant_2_db',
            'root'
        );

        $event = new TenantSwitchedEvent('tenant_2', $config);

        $this->assertNull($event->getPreviousTenantIdentifier());
        $this->assertNull($event->getPreviousDatabaseName());
    }
}
